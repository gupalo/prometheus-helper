<?php

namespace Gupalo\PrometheusHelper;

use JsonSerializable;
use Prometheus\Storage\InMemory;

class JsonAdapter extends InMemory implements JsonSerializable
{
    public function __construct(
        array $counters = [],
        array $gauges = [],
        array $histograms = [],
        array $summaries = [],
    ) {
        $this->counters = $counters;
        $this->gauges = $gauges;
        $this->histograms = $histograms;
        $this->summaries = $summaries;
    }

    public function jsonSerialize(): array
    {
        return [
            'counters' => $this->counters,
            'gauges' => $this->gauges,
            'histograms' => $this->histograms,
            'summaries' => $this->summaries,
        ];
    }

    public static function createFromJson(array $data): self
    {
        return new self(
            $data['counters'] ?? [],
            $data['gauges'] ?? [],
            $data['histograms'] ?? [],
            $data['summaries'] ?? [],
        );
    }
}
