<?php

namespace Gupalo\PrometheusHelper\Tests;

use Gupalo\PrometheusHelper\FileAdapter;
use Gupalo\PrometheusHelper\Prometheus;
use PHPUnit\Framework\TestCase;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\InMemory;

class PrometheusTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        $this->testDir = __DIR__ . '/tmp';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testDir . '/data.json')) {
            unlink($this->testDir . '/data.json');
        }
        if (is_dir($this->testDir)) {
            rmdir($this->testDir);
        }
    }

    public function testIncWithInMemoryAdapter(): void
    {
        $helper = new Prometheus(new InMemory(), 'test');

        $helper->inc('requests_total', 'Total requests');
        $helper->inc('requests_total', 'Total requests');

        $response = $helper->render();
        $content = $response->getContent();

        self::assertStringContainsString('test_requests_total', $content);
        self::assertStringContainsString('2', $content);
    }

    public function testIncWithLabels(): void
    {
        $helper = new Prometheus(new InMemory(), 'app');

        $helper->inc('http_requests_total', 'HTTP requests', ['method' => 'GET', 'status' => '200']);
        $helper->inc('http_requests_total', 'HTTP requests', ['method' => 'POST', 'status' => '201']);

        $response = $helper->render();
        $content = $response->getContent();

        self::assertStringContainsString('app_http_requests_total', $content);
        self::assertStringContainsString('method="GET"', $content);
        self::assertStringContainsString('method="POST"', $content);
    }

    public function testGaugeOperations(): void
    {
        $helper = new Prometheus(new InMemory(), 'test');

        $helper->gaugeSet(100, 'temperature', 'Current temperature');
        $helper->gaugeInc('temperature', 'Current temperature');
        $helper->gaugeDecBy(50, 'temperature', 'Current temperature');

        $response = $helper->render();
        $content = $response->getContent();

        self::assertStringContainsString('test_temperature', $content);
        self::assertStringContainsString('51', $content);
    }

    public function testHistogramObserve(): void
    {
        $helper = new Prometheus(new InMemory(), 'test');

        $helper->observe(0.5, 'request_duration_seconds', 'Request duration');
        $helper->observe(1.2, 'request_duration_seconds', 'Request duration');

        $response = $helper->render();
        $content = $response->getContent();

        self::assertStringContainsString('test_request_duration_seconds_bucket', $content);
        self::assertStringContainsString('test_request_duration_seconds_sum', $content);
        self::assertStringContainsString('test_request_duration_seconds_count', $content);
    }

    public function testWithFileAdapter(): void
    {
        $helper = new Prometheus(new FileAdapter($this->testDir), 'myapp');

        $helper->inc('events_total', 'Total events');

        unset($helper); // Trigger destructor to flush

        self::assertFileExists($this->testDir . '/data.json');
        $content = file_get_contents($this->testDir . '/data.json');
        self::assertStringContainsString('events_total', $content);

        // Test restore
        $helper2 = new Prometheus(new FileAdapter($this->testDir), 'myapp');
        $response = $helper2->render();
        self::assertStringContainsString('myapp_events_total', $response->getContent());
    }

    public function testGetRegistry(): void
    {
        $helper = new Prometheus(new InMemory(), 'test');

        $registry = $helper->getRegistry();
        $counter = $registry->registerCounter('test', 'direct_counter', 'Direct counter');
        $counter->inc();

        $response = $helper->render();
        self::assertStringContainsString('test_direct_counter', $response->getContent());
    }

    public function testGetAdapter(): void
    {
        $adapter = new InMemory();
        $helper = new Prometheus($adapter, 'test');

        self::assertSame($adapter, $helper->getAdapter());
    }
}
