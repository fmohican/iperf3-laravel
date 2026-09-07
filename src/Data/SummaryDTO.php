<?php

declare(strict_types=1);

namespace fmohican\Iperf3Laravel\Data;

use JsonSerializable;
use fmohican\Iperf3Laravel\Enums\ThroughputUnit;

final class SummaryDTO implements JsonSerializable
{
    public float $uploadSpeed {
        get => $this->uploadBitsPerSecond / $this->unit->divisor();
    }

    public float $downloadSpeed {
        get => $this->downloadBitsPerSecond / $this->unit->divisor();
    }

    public float $uploadSpeedMbps {
        get => $this->uploadBitsPerSecond / 1_000_000.0;
    }

    public float $downloadSpeedMbps {
        get => $this->downloadBitsPerSecond / 1_000_000.0;
    }

    public float $uploadSpeedGbps {
        get => $this->uploadBitsPerSecond / 1_000_000_000.0;
    }

    public float $downloadSpeedGbps {
        get => $this->downloadBitsPerSecond / 1_000_000_000.0;
    }

    public function __construct(
        public readonly string $protocol,
        public readonly float $durationSeconds,
        public readonly float $uploadBitsPerSecond,
        public readonly float $downloadBitsPerSecond,
        public readonly int $bytesSent,
        public readonly int $bytesReceived,
        public readonly int $retransmits,
        public readonly ?float $jitterMs,
        public readonly int $lostPackets,
        public readonly int $packets,
        public readonly float $lostPacketsPercent,
        public readonly ?float $averageRttMs,
        public readonly CpuUtilizationDTO $cpuUtilization,
        public readonly ThroughputUnit $unit = ThroughputUnit::MegabitsPerSecond,
    ) {}

    public function toBitsPerSecond(): self
    {
        return $this->withUnit(ThroughputUnit::BitsPerSecond);
    }

    public function toKbps(): self
    {
        return $this->withUnit(ThroughputUnit::KilobitsPerSecond);
    }

    public function toMbps(): self
    {
        return $this->withUnit(ThroughputUnit::MegabitsPerSecond);
    }

    public function toGbps(): self
    {
        return $this->withUnit(ThroughputUnit::GigabitsPerSecond);
    }

    /** @return array<string, float|int|string|null|CpuUtilizationDTO> */
    public function jsonSerialize(): array
    {
        return [
            'protocol' => $this->protocol->value,
            'duration_seconds' => $this->durationSeconds,
            'upload_bits_per_second' => $this->uploadBitsPerSecond,
            'download_bits_per_second' => $this->downloadBitsPerSecond,
            'upload_speed' => $this->uploadSpeed,
            'download_speed' => $this->downloadSpeed,
            'unit' => $this->unit->value,
            'upload_speed_mbps' => $this->uploadSpeedMbps,
            'download_speed_mbps' => $this->downloadSpeedMbps,
            'upload_speed_gbps' => $this->uploadSpeedGbps,
            'download_speed_gbps' => $this->downloadSpeedGbps,
            'bytes_sent' => $this->bytesSent,
            'bytes_received' => $this->bytesReceived,
            'retransmits' => $this->retransmits,
            'jitter_ms' => $this->jitterMs,
            'lost_packets' => $this->lostPackets,
            'packets' => $this->packets,
            'lost_packets_percent' => $this->lostPacketsPercent,
            'average_rtt_ms' => $this->averageRttMs,
            'cpu_utilization' => $this->cpuUtilization,
        ];
    }

    private function withUnit(ThroughputUnit $unit): self
    {
        return new self(
            protocol: $this->protocol,
            durationSeconds: $this->durationSeconds,
            uploadBitsPerSecond: $this->uploadBitsPerSecond,
            downloadBitsPerSecond: $this->downloadBitsPerSecond,
            bytesSent: $this->bytesSent,
            bytesReceived: $this->bytesReceived,
            retransmits: $this->retransmits,
            jitterMs: $this->jitterMs,
            lostPackets: $this->lostPackets,
            packets: $this->packets,
            lostPacketsPercent: $this->lostPacketsPercent,
            averageRttMs: $this->averageRttMs,
            cpuUtilization: $this->cpuUtilization,
            unit: $unit,
        );
    }
}
