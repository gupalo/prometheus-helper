<?php

namespace Gupalo\PrometheusHelper\Tests;

use Gupalo\PrometheusHelper\FileAdapter;
use PHPUnit\Framework\TestCase;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\Adapter;

class FileAdapterTest extends TestCase
{
    public function testCreateAndRestore(): void
    {
        $adapter = new FileAdapter(__DIR__);
        $adapter->updateCounter([
            'name' => 'test',
            'help' => '',
            'type' => 'counter',
            'labelNames' => [],
            'labelValues' => [],
            'value' => 1,
            'command' => Adapter::COMMAND_INCREMENT_INTEGER,
        ]);
        $adapter->updateGauge([
            'name' => 'test2',
            'help' => '',
            'type' => 'gauge',
            'labelNames' => [],
            'labelValues' => [],
            'value' => 1,
            'command' => Adapter::COMMAND_INCREMENT_FLOAT,
        ]);
        $adapter->updateHistogram([
            'value'       => 1,
            'name'        => 'test3',
            'help'        => '',
            'type'        => '',
            'labelNames'  => [],
            'labelValues' => [],
            'buckets'     => [],
        ]);
        $adapter->updateSummary([
            'value'         => 1,
            'name'          => 'test4',
            'help'          => '',
            'type'          => 'summary',
            'labelNames'    => [],
            'labelValues'   => [],
            'maxAgeSeconds' => 10,
            'quantiles'     => [],
        ]);

        unset($adapter); // call destructor

        self::assertFileExists(__DIR__ . '/data.json');

        $content = file_get_contents(__DIR__ . '/data.json');
        self::assertNotFalse($content);

        self::assertTrue(str_contains($content, 'counter:test:'));
        self::assertTrue(str_contains($content, 'gauge:test2:'));
        self::assertTrue(str_contains($content, ':test3:'));
        self::assertTrue(str_contains($content, ':test4:'));

        $adapterRestored = new FileAdapter(__DIR__);
        $renderer = new RenderTextFormat();
        $restoredResult = $renderer->render((new CollectorRegistry($adapterRestored))->getMetricFamilySamples());

        self::assertTrue(str_contains($restoredResult, 'test '));
        self::assertTrue(str_contains($restoredResult, 'test2 '));
        self::assertTrue(str_contains($restoredResult, 'test3_'));
        self::assertTrue(str_contains($restoredResult, 'test4_'));
    }

    public function tearDown(): void
    {
        !file_exists(__DIR__ . '/data.json') || unlink(__DIR__ . '/data.json');
    }
}
