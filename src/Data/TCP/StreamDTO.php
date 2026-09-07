<?php

declare(strict_types=1);

namespace fmohican\Iperf3Laravel\Data\TCP;

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
        public int $retransmits = 0,
        public ?int $congestionWindowBytes = null,
        public ?float $rttMicroseconds = null,
        public ?float $rttVarianceMicroseconds = null,
        public ?int $pathMtuBytes = null,
        public ?float $meanRttMicroseconds = null,
        public ?float $minimumRttMicroseconds = null,
        public ?float $maximumRttMicroseconds = null,
    ) {}

    /** @return array<string, bool|float|int|null> */
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
            'retransmits' => $this->retransmits,
            'congestion_window_bytes' => $this->congestionWindowBytes,
            'rtt_microseconds' => $this->rttMicroseconds,
            'rtt_variance_microseconds' => $this->rttVarianceMicroseconds,
            'path_mtu_bytes' => $this->pathMtuBytes,
            'mean_rtt_microseconds' => $this->meanRttMicroseconds,
            'minimum_rtt_microseconds' => $this->minimumRttMicroseconds,
            'maximum_rtt_microseconds' => $this->maximumRttMicroseconds,
        ];
    }
}
