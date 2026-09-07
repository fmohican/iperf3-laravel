<?php

declare(strict_types=1);

namespace fmohican\Iperf3Laravel\Data;

use JsonSerializable;

final readonly class ConnectionDTO implements JsonSerializable
{
    public function __construct(
        public int $socket,
        public string $localHost,
        public int $localPort,
        public string $remoteHost,
        public int $remotePort,
    ) {}

    /** @return array<string, int|string> */
    public function jsonSerialize(): array
    {
        return [
            'socket' => $this->socket,
            'local_host' => $this->localHost,
            'local_port' => $this->localPort,
            'remote_host' => $this->remoteHost,
            'remote_port' => $this->remotePort,
        ];
    }
}
