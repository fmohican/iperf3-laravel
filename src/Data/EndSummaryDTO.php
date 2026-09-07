<?php

declare(strict_types=1);

namespace fmohican\Iperf3Laravel\Data;

use JsonSerializable;
use fmohican\Iperf3Laravel\Data\TCP\EndStreamDTO as TcpEndStreamDTO;
use fmohican\Iperf3Laravel\Data\TCP\StreamDTO as TcpStreamDTO;
use fmohican\Iperf3Laravel\Data\UDP\EndStreamDTO as UdpEndStreamDTO;
use fmohican\Iperf3Laravel\Data\UDP\StreamDTO as UdpStreamDTO;

final readonly class EndSummaryDTO implements JsonSerializable
{
    /** @param list<TcpEndStreamDTO|UdpEndStreamDTO> $streams */
    public function __construct(
        public array $streams,
        public TcpStreamDTO|UdpStreamDTO|null $sumSent,
        public TcpStreamDTO|UdpStreamDTO|null $sumReceived,
        public TcpStreamDTO|UdpStreamDTO|null $sum,
        public CpuUtilizationDTO $cpuUtilization,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'streams' => $this->streams,
            'sum_sent' => $this->sumSent,
            'sum_received' => $this->sumReceived,
            'sum' => $this->sum,
            'cpu_utilization' => $this->cpuUtilization,
        ];
    }
}
