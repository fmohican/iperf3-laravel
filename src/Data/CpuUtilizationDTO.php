<?php

declare(strict_types=1);

namespace fmohican\Iperf3Laravel\Data;

use JsonSerializable;

final readonly class CpuUtilizationDTO implements JsonSerializable
{
    public function __construct(
        public float $hostTotal,
        public float $hostUser,
        public float $hostSystem,
        public float $remoteTotal,
        public float $remoteUser,
        public float $remoteSystem,
    ) {}

    /** @return array<string, float> */
    public function jsonSerialize(): array
    {
        return [
            'host_total' => $this->hostTotal,
            'host_user' => $this->hostUser,
            'host_system' => $this->hostSystem,
            'remote_total' => $this->remoteTotal,
            'remote_user' => $this->remoteUser,
            'remote_system' => $this->remoteSystem,
        ];
    }
}
