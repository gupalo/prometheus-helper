<?php

declare(strict_types=1);

namespace Gupalo\PrometheusHelper;

use Gupalo\Json\Json;
use RuntimeException;

class FileAdapter extends JsonAdapter
{
    private string $filenameData;

    public function __construct(string $dir)
    {
        $dir = $this->ensureDir($dir);

        $this->filenameData = sprintf('%s/data.json', $dir);
        $json = $this->loadJsonFromFile();

        parent::__construct(
            $json['counters'] ?? [],
            $json['gauges'] ?? [],
            $json['histograms'] ?? [],
            $json['summaries'] ?? [],
        );
    }

    public function __destruct()
    {
        $this->flush();
    }

    private function flush(): void
    {
        file_put_contents($this->filenameData, Json::toString($this));
    }

    private function loadJsonFromFile(): array
    {
        if (!is_file($this->filenameData)) {
            return [];
        }

        $data = file_get_contents($this->filenameData);

        try {
            $result = Json::toArray($data);
        } catch (\Throwable) {
            $result = [];
            $this->wipeStorage();
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

    public function wipeStorage(): void
    {
        $this->counters = [];
        $this->gauges = [];
        $this->histograms = [];
        $this->summaries = [];

        if ($this->filenameData) {
            try {
                $this->ensureDir(dirname($this->filenameData));
                file_put_contents($this->filenameData, '{}');
            } catch (\Throwable) {
                // do nothing
            }
        }
    }
}
