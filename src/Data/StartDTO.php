<?php

declare(strict_types=1);

namespace fmohican\Iperf3Laravel\Data;

use JsonSerializable;
use fmohican\Iperf3Laravel\Enums\Protocol;

final readonly class StartDTO implements JsonSerializable
{
    /**
     * @param  list<ConnectionDTO>  $connections
     */
    public function __construct(
        public string $version,
        public string $systemInfo,
        public ?string $timestamp,
        public ?int $timestampSeconds,
        public string $targetHost,
        public int $targetPort,
        public Protocol $protocol,
        public int $numberOfStreams,
        public float $durationSeconds,
        public float $intervalSeconds,
        public bool $reverse,
        public bool $bidirectional,
        public array $connections,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'version' => $this->version,
            'system_info' => $this->systemInfo,
            'timestamp' => $this->timestamp,
            'timestamp_seconds' => $this->timestampSeconds,
            'target_host' => $this->targetHost,
            'target_port' => $this->targetPort,
            'protocol' => $this->protocol->value,
            'number_of_streams' => $this->numberOfStreams,
            'duration_seconds' => $this->durationSeconds,
            'interval_seconds' => $this->intervalSeconds,
            'reverse' => $this->reverse,
            'bidirectional' => $this->bidirectional,
            'connections' => $this->connections,
        ];
    }
}
