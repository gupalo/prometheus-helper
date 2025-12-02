<?php

namespace Gupalo\PrometheusHelper;

use Prometheus\CollectorRegistry;
use Prometheus\Counter;
use Prometheus\Exception\MetricNotFoundException;
use Prometheus\Exception\MetricsRegistrationException;
use Prometheus\Gauge;
use Prometheus\Histogram;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\Adapter;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class Prometheus
{
    private CollectorRegistry $registry;
    private ?string $lastError = null;

    public function __construct(
        private readonly Adapter $adapter,
        private readonly string $namespace = 'app',
    ) {
        $this->registry = new CollectorRegistry($this->adapter);
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function getRegistry(): CollectorRegistry
    {
        return $this->registry;
    }

    public function getAdapter(): Adapter
    {
        return $this->adapter;
    }

    /**
     * @param string $name e.g. requests
     * @param string|null $help e.g. The number of requests made.
     * @param array $labels e.g. ['controller' => 'myController', 'action' => 'myAction']
     */
    public function inc(string $name, ?string $help = null, array $labels = []): void
    {
        $this->incBy(1, $name, $help ?? $name, $labels);
    }

    /**
     * @param int $count e.g. 2
     * @param string $name e.g. requests
     * @param string|null $help e.g. The number of requests made.
     * @param array $labels e.g. ['controller' => 'myController', 'action' => 'myAction']
     */
    public function incBy(int $count, string $name, ?string $help = null, array $labels = []): void
    {
        $this->lastError = null;
        if (!$count) {
            return;
        }

        try {
            $this->getCounter($name, $help ?? $name, array_keys($labels))->incBy($count, array_values($labels));
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
        }
    }

    /**
     * @param float $value e.g. 123
     * @param string $name e.g. requests
     * @param string|null $help e.g. The number of requests made.
     * @param array $labels e.g. ['controller' => 'myController', 'action' => 'myAction']
     */
    public function set(float $value, string $name, ?string $help = null, array $labels = []): void
    {
        $this->lastError = null;
        try {
            $this->getGauge($name, $help ?? $name, array_keys($labels))->set($value, array_values($labels));
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
        }
    }

    /**
     * @param string $name e.g. requests
     * @param string|null $help e.g. The number of requests made.
     * @param array $labels e.g. ['controller' => 'myController', 'action' => 'myAction']
     */
    public function gaugeInc(string $name, ?string $help = null, array $labels = []): void
    {
        $this->gaugeIncBy(1, $name, $help ?? $name, $labels);
    }

    /**
     * @param float|int $count e.g. 2
     * @param string $name e.g. requests
     * @param string|null $help e.g. The number of requests made.
     * @param array $labels e.g. ['controller' => 'myController', 'action' => 'myAction']
     */
    public function gaugeIncBy(float|int $count, string $name, ?string $help = null, array $labels = []): void
    {
        $this->lastError = null;
        if (!$count) {
            return;
        }

        try {
            $this->getGauge($name, $help ?? $name, array_keys($labels))->incBy($count, array_values($labels));
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
        }
    }

    /**
     * @param string $name e.g. requests
     * @param string|null $help e.g. The number of requests made.
     * @param array $labels e.g. ['controller' => 'myController', 'action' => 'myAction']
     */
    public function gaugeDec(string $name, ?string $help = null, array $labels = []): void
    {
        $this->gaugeDecBy(1, $name, $help ?? $name, $labels);
    }

    /**
     * @param float|int $count e.g. 2
     * @param string $name e.g. requests
     * @param string|null $help e.g. The number of requests made.
     * @param array $labels e.g. ['controller' => 'myController', 'action' => 'myAction']
     */
    public function gaugeDecBy(float|int $count, string $name, ?string $help = null, array $labels = []): void
    {
        $this->lastError = null;
        if (!$count) {
            return;
        }

        try {
            $this->getGauge($name, $help ?? $name, array_keys($labels))->decBy($count, array_values($labels));
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
        }
    }

    /**
     * @param float $value e.g. 2.2
     * @param string $name e.g. requests
     * @param string|null $help e.g. The number of requests made.
     * @param array $labels e.g. ['controller' => 'myController', 'action' => 'myAction']
     */
    public function gaugeSet(float $value, string $name, ?string $help = null, array $labels = []): void
    {
        $this->lastError = null;

        try {
            $this->getGauge($name, $help ?? $name, array_keys($labels))->set($value, array_values($labels));
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
        }
    }

    /**
     * @param float $value e.g. 123
     * @param string $name e.g. duration_seconds
     * @param string|null $help e.g. A histogram of the duration in seconds.
     * @param array $labels e.g. ['controller' => 'myController', 'action' => 'myAction']
     * @param array|null $buckets e.g. [100, 200, 300]
     */
    public function observe(float $value, string $name, ?string $help = null, array $labels = [], ?array $buckets = null): void
    {
        $this->lastError = null;
        try {
            $this->getHistogram($name, $help ?? $name, array_keys($labels), $buckets)
                ->observe($value, array_values($labels));
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
        }
    }

    public function render(): Response
    {
        $renderer = new RenderTextFormat();
        $result = $renderer->render($this->registry->getMetricFamilySamples());

        return new Response($result, Response::HTTP_OK, [
            'Content-Type' => RenderTextFormat::MIME_TYPE,
        ]);
    }

    /**
     * @param string $name e.g. requests
     * @param string|null $help e.g. The number of requests made.
     * @param array $labels e.g. ['controller', 'action']
     * @throws MetricsRegistrationException
     */
    private function getCounter(string $name, ?string $help = null, array $labels = []): Counter
    {
        try {
            $counter = $this->registry->getCounter($this->namespace, $name);
        } catch (MetricNotFoundException) {
            $counter = $this->registry->registerCounter($this->namespace, $name, $help ?? $name, $labels);
        }

        return $counter;
    }

    /**
     * @param string $name e.g. duration_seconds
     * @param string|null $help e.g. The duration something took in seconds.
     * @param array $labels e.g. ['controller', 'action']
     * @throws MetricsRegistrationException
     */
    private function getGauge(string $name, ?string $help = null, array $labels = []): Gauge
    {
        try {
            $gauge = $this->registry->getGauge($this->namespace, $name);
        } catch (MetricNotFoundException) {
            $gauge = $this->registry->registerGauge($this->namespace, $name, $help ?? $name, $labels);
        }

        return $gauge;
    }

    /**
     * @param string $name e.g. duration_seconds
     * @param string|null $help e.g. A histogram of the duration in seconds.
     * @param array $labels e.g. ['controller', 'action']
     * @param array|null $buckets e.g. [100, 200, 300]
     * @throws MetricsRegistrationException
     */
    private function getHistogram(string $name, ?string $help = null, array $labels = [], ?array $buckets = null): Histogram
    {
        try {
            $histogram = $this->registry->getHistogram($this->namespace, $name);
        } catch (MetricNotFoundException) {
            $histogram = $this->registry->registerHistogram(
                namespace: $this->namespace,
                name: $name,
                help: $help ?? $name,
                labels: $labels,
                buckets: $buckets,
            );
        }

        return $histogram;
    }
}
