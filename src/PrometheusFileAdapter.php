<?php

declare(strict_types=1);

namespace Gupalo\PrometheusHelper;

use Prometheus\MetricFamilySamples;
use Prometheus\Storage\Adapter;
use RuntimeException;
use Throwable;

class PrometheusFileAdapter implements Adapter
{
    private array $counters = [];
    private array $gauges = [];
    private array $histograms = [];

    private string $filenameCounters;
    private string $filenameGauges;
    private string $filenameHistograms;

    private string $fileCountersMd5 = '';
    private string $fileGaugesMd5 = '';
    private string $fileHistogramsMd5 = '';

    public function __construct(string $dir)
    {
        $dir = $this->ensureDir($dir);

        $this->filenameCounters = sprintf('%s/counters.json', $dir);
        $this->filenameGauges = sprintf('%s/gauges.json', $dir);
        $this->filenameHistograms = sprintf('%s/histograms.json', $dir);
        $this->load();
    }

    private function load(): void
    {
        $this->loadCounters();
        $this->loadGauges();
        $this->loadHistograms();
    }

    private function loadCounters(): void
    {
        if (!is_file($this->filenameCounters)) {
            $this->counters = [];

            return;
        }
        $s = file_get_contents($this->filenameCounters);
        $md5 = md5($s);
        if ($md5 === $this->fileCountersMd5) {
            return;
        }
        $this->fileCountersMd5 = $md5;
        try {
            $this->counters = json_decode($s, true, 10, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            $this->counters = [];
        }
    }

    private function loadGauges(): void
    {
        if (!is_file($this->filenameGauges)) {
            $this->gauges = [];

            return;
        }
        $s = file_get_contents($this->filenameGauges);
        $md5 = md5($s);
        if ($md5 === $this->fileGaugesMd5) {
            return;
        }
        $this->fileGaugesMd5 = $md5;
        try {
            $this->gauges = json_decode($s, true, 10, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            $this->gauges = [];
        }
    }

    private function loadHistograms(): void
    {
        if (!is_file($this->filenameHistograms)) {
            $this->histograms = [];

            return;
        }
        $s = file_get_contents($this->filenameHistograms);
        $md5 = md5($s);
        if ($md5 === $this->fileHistogramsMd5) {
            return;
        }
        $this->fileHistogramsMd5 = $md5;
        try {
            $this->histograms = json_decode($s, true, 10, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            $this->histograms = [];
        }
    }

    private function saveCounters(): void
    {
        try {
            $s = json_encode($this->counters, JSON_THROW_ON_ERROR, 10);
            $this->fileCountersMd5 = md5($s);
            file_put_contents($this->filenameCounters, $s);
        } catch (Throwable $e) {
        }
    }

    private function saveGauges(): void
    {
        try {
            $s = json_encode($this->gauges, JSON_THROW_ON_ERROR, 10);
            $this->fileGaugesMd5 = md5($s);
            file_put_contents($this->filenameGauges, $s);
        } catch (Throwable $e) {
        }
    }

    private function saveHistograms(): void
    {
        try {
            $s = json_encode($this->histograms, JSON_THROW_ON_ERROR, 10);
            $this->fileHistogramsMd5 = md5($s);
            file_put_contents($this->filenameHistograms, $s);
        } catch (Throwable $e) {
        }
    }

    /**
     * @return MetricFamilySamples[]
     */
    public function collect(): array
    {
        $this->loadCounters();
        $metrics = $this->internalCollect($this->counters);

        $this->loadGauges();
        $metrics = array_merge($metrics, $this->internalCollect($this->gauges));

        $this->loadHistograms();
        $metrics = array_merge($metrics, $this->collectHistograms());

        return $metrics;
    }

    private function collectHistograms(): array
    {
        $histograms = [];
        foreach ($this->histograms as $histogram) {
            $metaData = $histogram['meta'];
            $data = [
                'name' => $metaData['name'],
                'help' => $metaData['help'],
                'type' => $metaData['type'],
                'labelNames' => $metaData['labelNames'],
                'buckets' => $metaData['buckets'],
            ];

            // Add the Inf bucket so we can compute it later on
            $data['buckets'][] = '+Inf';

            $histogramBuckets = [];
            foreach ($histogram['samples'] as $key => $value) {
                $parts = explode(':', $key);
                $labelValues = $parts[2];
                $bucket = $parts[3];
                // Key by labelValues
                $histogramBuckets[$labelValues][$bucket] = $value;
            }

            // Compute all buckets
            $labels = array_keys($histogramBuckets);
            sort($labels);
            foreach ($labels as $labelValues) {
                $acc = 0;
                $decodedLabelValues = $this->decodeLabelValues($labelValues);
                foreach ($data['buckets'] as $bucket) {
                    $bucket = (string)$bucket;
                    if (!isset($histogramBuckets[$labelValues][$bucket])) {
                        $data['samples'][] = [
                            'name' => $metaData['name'].'_bucket',
                            'labelNames' => ['le'],
                            'labelValues' => array_merge($decodedLabelValues, [$bucket]),
                            'value' => $acc,
                        ];
                    } else {
                        $acc += $histogramBuckets[$labelValues][$bucket];
                        $data['samples'][] = [
                            'name' => $metaData['name'].'_'.'bucket',
                            'labelNames' => ['le'],
                            'labelValues' => array_merge($decodedLabelValues, [$bucket]),
                            'value' => $acc,
                        ];
                    }
                }

                // Add the count
                $data['samples'][] = [
                    'name' => $metaData['name'].'_count',
                    'labelNames' => [],
                    'labelValues' => $decodedLabelValues,
                    'value' => $acc,
                ];

                // Add the sum
                $data['samples'][] = [
                    'name' => $metaData['name'].'_sum',
                    'labelNames' => [],
                    'labelValues' => $decodedLabelValues,
                    'value' => $histogramBuckets[$labelValues]['sum'],
                ];
            }
            $histograms[] = new MetricFamilySamples($data);
        }

        return $histograms;
    }

    private function internalCollect(array $metrics): array
    {
        $result = [];
        foreach ($metrics as $metric) {
            $metaData = $metric['meta'];
            $data = [
                'name' => $metaData['name'],
                'help' => $metaData['help'],
                'type' => $metaData['type'],
                'labelNames' => $metaData['labelNames'],
            ];
            foreach ($metric['samples'] as $key => $value) {
                $parts = explode(':', $key);
                $labelValues = $parts[2];
                $data['samples'][] = [
                    'name' => $metaData['name'],
                    'labelNames' => [],
                    'labelValues' => $this->decodeLabelValues($labelValues),
                    'value' => $value,
                ];
            }
            $this->sortSamples($data['samples']);
            $result[] = new MetricFamilySamples($data);
        }

        return $result;
    }

    public function updateHistogram(array $data): void
    {
        $this->loadHistograms();

        // Initialize the sum
        $metaKey = $this->metaKey($data);
        if (array_key_exists($metaKey, $this->histograms) === false) {
            $this->histograms[$metaKey] = [
                'meta' => $this->metaData($data),
                'samples' => [],
            ];
        }
        $sumKey = $this->histogramBucketValueKey($data, 'sum');
        if (array_key_exists($sumKey, $this->histograms[$metaKey]['samples']) === false) {
            $this->histograms[$metaKey]['samples'][$sumKey] = 0;
        }

        $this->histograms[$metaKey]['samples'][$sumKey] += $data['value'];


        $bucketToIncrease = '+Inf';
        foreach ($data['buckets'] as $bucket) {
            if ($data['value'] <= $bucket) {
                $bucketToIncrease = $bucket;
                break;
            }
        }

        $bucketKey = $this->histogramBucketValueKey($data, $bucketToIncrease);
        if (array_key_exists($bucketKey, $this->histograms[$metaKey]['samples']) === false) {
            $this->histograms[$metaKey]['samples'][$bucketKey] = 0;
        }
        $this->histograms[$metaKey]['samples'][$bucketKey] += 1;

        $this->saveHistograms();
    }

    public function updateGauge(array $data): void
    {
        $this->loadGauges();

        $metaKey = $this->metaKey($data);
        $valueKey = $this->valueKey($data);
        if (array_key_exists($metaKey, $this->gauges) === false) {
            $this->gauges[$metaKey] = [
                'meta' => $this->metaData($data),
                'samples' => [],
            ];
        }
        if (array_key_exists($valueKey, $this->gauges[$metaKey]['samples']) === false) {
            $this->gauges[$metaKey]['samples'][$valueKey] = 0;
        }
        if ($data['command'] === Adapter::COMMAND_SET) {
            $this->gauges[$metaKey]['samples'][$valueKey] = $data['value'];
        } else {
            $this->gauges[$metaKey]['samples'][$valueKey] += $data['value'];
        }

        $this->saveGauges();
    }

    public function updateCounter(array $data): void
    {
        $this->loadCounters();

        $metaKey = $this->metaKey($data);
        $valueKey = $this->valueKey($data);
        if (array_key_exists($metaKey, $this->counters) === false) {
            $this->counters[$metaKey] = [
                'meta' => $this->metaData($data),
                'samples' => [],
            ];
        }
        if (array_key_exists($valueKey, $this->counters[$metaKey]['samples']) === false) {
            $this->counters[$metaKey]['samples'][$valueKey] = 0;
        }
        if ($data['command'] === Adapter::COMMAND_SET) {
            $this->counters[$metaKey]['samples'][$valueKey] = 0;
        } else {
            $this->counters[$metaKey]['samples'][$valueKey] += $data['value'];
        }

        $this->saveCounters();
    }

    private function histogramBucketValueKey(array $data, string $bucket): string
    {
        return implode(':', [
            $data['type'],
            $data['name'],
            $this->encodeLabelValues($data['labelValues']),
            $bucket,
        ]);
    }

    private function metaKey(array $data): string
    {
        return implode(':', [
            $data['type'],
            $data['name'],
            'meta',
        ]);
    }

    private function valueKey(array $data): string
    {
        return implode(':', [
            $data['type'],
            $data['name'],
            $this->encodeLabelValues($data['labelValues']),
            'value',
        ]);
    }

    private function metaData(array $data): array
    {
        $metricsMetaData = $data;
        unset($metricsMetaData['value'], $metricsMetaData['command'], $metricsMetaData['labelValues']);

        return $metricsMetaData;
    }

    private function sortSamples(array &$samples): void
    {
        usort($samples, static function ($a, $b) {
            return strcmp(implode('', $a['labelValues']), implode("", $b['labelValues']));
        });
    }

    private function encodeLabelValues(array $values): string
    {
        try {
            $json = json_encode($values, JSON_THROW_ON_ERROR, 10);
        } catch (Throwable $e) {
            throw new RuntimeException($e->getMessage());
        }

        return base64_encode($json);
    }

    private function decodeLabelValues($values): array
    {
        $json = base64_decode($values, true);
        if (false === $json) {
            throw new RuntimeException('Cannot base64 decode label values');
        }

        try {
            $result = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            throw new RuntimeException($e->getMessage());
        }

        return $result;
    }

    protected function ensureDir(string $dir): string
    {
        $dir = rtrim($dir, '/');
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }

        return $dir;
    }
}
