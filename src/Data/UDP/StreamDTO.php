<?php

declare(strict_types=1);

namespace fmohican\Iperf3Laravel\Data\UDP;

use JsonSerializable;

final readonly class StreamDTO implements JsonSerializable
{
    public function __construct(
        public int $socket,
        public float $startSeconds,
        public float $endSeconds,
        public float $seconds,
        public int $bytes,
        public float $bitsPerSecond,
        public bool $omitted,
        public bool $sender,
        public float $jitterMs = 0.0,
        public int $lostPackets = 0,
        public int $packets = 0,
        public float $lostPacketsPercent = 0.0,
        public int $outOfOrderPackets = 0,
    ) {}

    /** @return array<string, bool|float|int> */
    public function jsonSerialize(): array
    {
        return [
            'socket' => $this->socket,
            'start_seconds' => $this->startSeconds,
            'end_seconds' => $this->endSeconds,
            'seconds' => $this->seconds,
            'bytes' => $this->bytes,
            'bits_per_second' => $this->bitsPerSecond,
            'omitted' => $this->omitted,
            'sender' => $this->sender,
            'jitter_ms' => $this->jitterMs,
            'lost_packets' => $this->lostPackets,
            'packets' => $this->packets,
            'lost_packets_percent' => $this->lostPacketsPercent,
            'out_of_order_packets' => $this->outOfOrderPackets,
        ];
    }
}
