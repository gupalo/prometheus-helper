<?php

namespace Gupalo\PrometheusHelper;

use Prometheus\CollectorRegistry;
use Prometheus\Counter;
use Prometheus\Exception\MetricNotFoundException;
use Prometheus\Exception\MetricsRegistrationException;
use Prometheus\Gauge;
use Prometheus\Histogram;
use Prometheus\RenderTextFormat;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PrometheusHelper
{
    private static string $namespace = 'app';

    private static ?string $dir = null;

    public static ?string $lastError = null;

    public static ?string $class = null;

    /**
     * @param string $name e.g. requests
     * @param ?string $help e.g. The number of requests made.
     * @param array $labels e.g. ['controller' => 'myController', 'action' => 'myAction']
     */
    public static function inc($name, string $help = null, array $labels = []): void
    {
        self::incBy(1, $name, $help ?? $name, $labels);
    }

    /**
     * @param int $count e.g. 2
     * @param string $name e.g. requests
     * @param ?string $help e.g. The number of requests made.
     * @param array $labels e.g. ['controller' => 'myController', 'action' => 'myAction']
     */
    public static function incBy($count, $name, string $help = null, array $labels = []): void
    {
        self::$lastError = null;
        if (!$count) {
            return;
        }

        try {
            self::getCounter($name, $help ?? $name, array_keys($labels))->incBy($count, array_values($labels));
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
        }
    }

    /**
     * @param double $value e.g. 123
     * @param string $name e.g. requests
     * @param ?string $help e.g. The number of requests made.
     * @param array $labels e.g. ['controller' => 'myController', 'action' => 'myAction']
     */
    public static function set(float $value, $name, string $help = null, array $labels = []): void
    {
        self::$lastError = null;
        try {
            self::getGauge($name, $help ?? $name, array_keys($labels))->set($value, array_values($labels));
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
        }
    }

    /**
     * @param string $name e.g. requests
     * @param ?string $help e.g. The number of requests made.
     * @param array $labels e.g. ['controller' => 'myController', 'action' => 'myAction']
     */
    public static function gaugeInc($name, string $help = null, array $labels = []): void
    {
        self::gaugeIncBy(1, $name, $help ?? $name, $labels);
    }

    /**
     * @param int|float $count e.g. 2
     * @param string $name e.g. requests
     * @param ?string $help e.g. The number of requests made.
     * @param array $labels e.g. ['controller' => 'myController', 'action' => 'myAction']
     */
    public static function gaugeIncBy($count, $name, string $help = null, array $labels = []): void
    {
        self::$lastError = null;
        if (!$count) {
            return;
        }

        try {
            self::getGauge($name, $help ?? $name, array_keys($labels))->incBy($count, array_values($labels));
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
        }
    }

    /**
     * @param string $name e.g. requests
     * @param ?string $help e.g. The number of requests made.
     * @param array $labels e.g. ['controller' => 'myController', 'action' => 'myAction']
     */
    public static function gaugeDec($name, string $help = null, array $labels = []): void
    {
        self::gaugeDecBy(1, $name, $help ?? $name, $labels);
    }

    /**
     * @param int|float $count e.g. 2
     * @param string $name e.g. requests
     * @param ?string $help e.g. The number of requests made.
     * @param array $labels e.g. ['controller' => 'myController', 'action' => 'myAction']
     */
    public static function gaugeDecBy($count, $name, string $help = null, array $labels = []): void
    {
        self::$lastError = null;
        if (!$count) {
            return;
        }

        try {
            self::getGauge($name, $help ?? $name, array_keys($labels))->decBy($count, array_values($labels));
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
        }
    }

    /**
     * @param float $value e.g. 2.2
     * @param string $name e.g. requests
     * @param ?string $help e.g. The number of requests made.
     * @param array $labels e.g. ['controller' => 'myController', 'action' => 'myAction']
     */
    public static function gaugeSet(float $value, $name, string $help = null, array $labels = []): void
    {
        self::$lastError = null;

        try {
            self::getGauge($name, $help ?? $name, array_keys($labels))->set($value, array_values($labels));
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
        }
    }

    /**
     * @param double $value e.g. 123
     * @param string $name e.g. duration_seconds
     * @param ?string $help e.g. A histogram of the duration in seconds.
     * @param array $labels e.g. ['controller' => 'myController', 'action' => 'myAction']
     * @param ?array $buckets e.g. [100, 200, 300]
     */
    public static function observe(float $value, $name, string $help = null, $labels = [], $buckets = null): void
    {
        self::$lastError = null;
        try {
            self::getHistogram($name, $help ?? $name, array_keys($labels), $buckets)
                ->observe($value, array_values($labels));
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
        }
    }

    public static function render(): Response
    {
        $renderer = new RenderTextFormat();
        $result = $renderer->render(self::getPrometheus()->getMetricFamilySamples());

        return new Response($result, Response::HTTP_OK, [
            'Content-Type' => RenderTextFormat::MIME_TYPE,
        ]);
    }

    /**
     * @param string $name e.g. requests
     * @param ?string $help e.g. The number of requests made.
     * @param array $labels e.g. ['controller', 'action']
     * @return Counter
     * @throws MetricsRegistrationException
     */
    private static function getCounter($name, string $help = null, $labels = []): Counter
    {
        try {
            $counter = self::getPrometheus()->getCounter(self::$namespace, $name);
        } catch (MetricNotFoundException $e) {
            $counter = self::getPrometheus()->registerCounter(self::$namespace, $name, $help ?? $name, $labels);
        }

        return $counter;
    }

    /**
     * @param string $name e.g. duration_seconds
     * @param ?string $help e.g. The duration something took in seconds.
     * @param array $labels e.g. ['controller', 'action']
     * @return Gauge
     * @throws MetricsRegistrationException
     */
    private static function getGauge($name, string $help = null, $labels = []): Gauge
    {
        try {
            $gauge = self::getPrometheus()->getGauge(self::$namespace, $name);
        } catch (MetricNotFoundException $e) {
            $gauge = self::getPrometheus()->registerGauge(self::$namespace, $name, $help ?? $name, $labels);
        }

        return $gauge;
    }

    /**
     * @param string $name e.g. duration_seconds
     * @param ?string $help e.g. A histogram of the duration in seconds.
     * @param array $labels e.g. ['controller', 'action']
     * @param ?array $buckets e.g. [100, 200, 300]
     * @return Histogram
     * @throws MetricsRegistrationException
     */
    private static function getHistogram($name, string $help = null, $labels = [], $buckets = null): Histogram
    {
        try {
            $histogram = self::getPrometheus()->getHistogram(self::$namespace, $name);
        } catch (MetricNotFoundException $e) {
            $histogram = self::getPrometheus()->registerHistogram(
                self::$namespace,
                $name,
                $help ?? $name,
                $labels,
                $buckets
            );
        }

        return $histogram;
    }

    public static function getPrometheus(): CollectorRegistry
    {
        static $registry = null;
        if ($registry === null) {
            $registry = new CollectorRegistry(new FileAdapter(self::getDir()));
        }

        return $registry;
    }

    public static function setNamespace(string $namespace): void
    {
        self::$namespace = $namespace;
    }

    public static function setDir(string $dir): void
    {
        self::$dir = $dir;
    }

    private static function getDir(): string
    {
        if (self::$dir === null) {
            $possibleDirs = [
                ['/app', '/app/var/prom'],
                ['/code', '/code/var/prom'],
                ['/tmp', '/tmp/prom'],
                ['c:/tmp', 'c:/tmp/prom'],
            ];
            foreach ($possibleDirs as $item) {
                [$baseDir, $dir] = $item;
                if (is_dir($baseDir)) {
                    self::$dir = $dir;
                    break;
                }
            }
            if (self::$dir === null) {
                throw new \RuntimeException('prometheus_missing_dir');
            }
        }

        return self::$dir;
    }
}
