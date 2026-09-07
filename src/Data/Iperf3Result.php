<?php

declare(strict_types=1);

namespace fmohican\Iperf3Laravel\Data;

use JsonSerializable;
use fmohican\Iperf3Laravel\Data\TCP\EndStreamDTO as TcpEndStreamDTO;
use fmohican\Iperf3Laravel\Data\TCP\IntervalDTO as TcpIntervalDTO;
use fmohican\Iperf3Laravel\Data\TCP\StreamDTO as TcpStreamDTO;
use fmohican\Iperf3Laravel\Data\UDP\IntervalDTO as UdpIntervalDTO;
use fmohican\Iperf3Laravel\Data\UDP\StreamDTO as UdpStreamDTO;
use fmohican\Iperf3Laravel\Enums\Protocol;

final readonly class Iperf3Result implements JsonSerializable
{
    /** @param list<TcpIntervalDTO|UdpIntervalDTO> $intervals */
    public function __construct(
        public StartDTO $start,
        public array $intervals,
        public EndSummaryDTO $end,
    ) {}

    public function summary(): SummaryDTO
    {
        return match ($this->start->protocol) {
            Protocol::TCP => $this->tcpSummary(),
            Protocol::UDP => $this->udpSummary(),
        };
    }

    /** @return array{start: StartDTO, intervals: list<TcpIntervalDTO|UdpIntervalDTO>, end: EndSummaryDTO} */
    public function jsonSerialize(): array
    {
        return [
            'start' => $this->start,
            'intervals' => $this->intervals,
            'end' => $this->end,
        ];
    }

    private function tcpSummary(): SummaryDTO
    {
        $sent = $this->end->sumSent instanceof TcpStreamDTO ? $this->end->sumSent : null;
        $received = $this->end->sumReceived instanceof TcpStreamDTO ? $this->end->sumReceived : null;
        $aggregate = $this->end->sum instanceof TcpStreamDTO ? $this->end->sum : null;
        $sent ??= $aggregate;
        $received ??= $aggregate;

        $rttTotal = 0.0;
        $rttSamples = 0;

        if ($sent?->meanRttMicroseconds !== null) {
            $rttTotal = $sent->meanRttMicroseconds;
            $rttSamples = 1;
        } else {
            foreach ($this->end->streams as $endStream) {
                if ($endStream instanceof TcpEndStreamDTO
                    && $endStream->sender?->meanRttMicroseconds !== null) {
                    $rttTotal += $endStream->sender->meanRttMicroseconds;
                    $rttSamples++;
                }
            }
        }

        if ($rttSamples === 0) {
            foreach ($this->intervals as $interval) {
                if (! $interval instanceof TcpIntervalDTO) {
                    continue;
                }

                foreach ($interval->streams as $stream) {
                    if (! $stream->omitted && $stream->rttMicroseconds !== null) {
                        $rttTotal += $stream->rttMicroseconds;
                        $rttSamples++;
                    }
                }
            }
        }

        return new SummaryDTO(
            protocol: Protocol::TCP->value,
            durationSeconds: max($sent?->seconds ?? 0.0, $received?->seconds ?? 0.0),
            uploadBitsPerSecond: $sent?->bitsPerSecond ?? 0.0,
            downloadBitsPerSecond: $received?->bitsPerSecond ?? 0.0,
            bytesSent: $sent?->bytes ?? 0,
            bytesReceived: $received?->bytes ?? 0,
            retransmits: $sent?->retransmits ?? 0,
            jitterMs: null,
            lostPackets: 0,
            packets: 0,
            lostPacketsPercent: 0.0,
            averageRttMs: $rttSamples > 0 ? ($rttTotal / $rttSamples) / 1_000.0 : null,
            cpuUtilization: $this->end->cpuUtilization,
        );
    }

    private function udpSummary(): SummaryDTO
    {
        $sum = $this->end->sum instanceof UdpStreamDTO ? $this->end->sum : null;
        $sent = $this->end->sumSent instanceof UdpStreamDTO ? $this->end->sumSent : null;
        $received = $this->end->sumReceived instanceof UdpStreamDTO ? $this->end->sumReceived : null;
        $metrics = $sum ?? $received ?? $sent;

        $upload = $sent?->bitsPerSecond ?? 0.0;
        $download = $received?->bitsPerSecond ?? 0.0;

        if ($sum !== null && $sent === null && $received === null) {
            if ($this->start->reverse) {
                $download = $sum->bitsPerSecond;
            } else {
                $upload = $sum->bitsPerSecond;
            }
        }

        return new SummaryDTO(
            protocol: Protocol::UDP->value,
            durationSeconds: $metrics?->seconds ?? 0.0,
            uploadBitsPerSecond: $upload,
            downloadBitsPerSecond: $download,
            bytesSent: $sent?->bytes ?? (! $this->start->reverse ? $sum?->bytes ?? 0 : 0),
            bytesReceived: $received?->bytes ?? ($this->start->reverse ? $sum?->bytes ?? 0 : 0),
            retransmits: 0,
            jitterMs: $metrics?->jitterMs,
            lostPackets: $metrics?->lostPackets ?? 0,
            packets: $metrics?->packets ?? 0,
            lostPacketsPercent: $metrics?->lostPacketsPercent ?? 0.0,
            averageRttMs: null,
            cpuUtilization: $this->end->cpuUtilization,
        );
    }
}
