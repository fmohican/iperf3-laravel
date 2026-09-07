<?php

declare(strict_types=1);

namespace fmohican\Iperf3Laravel\Data\UDP;

use JsonSerializable;

final readonly class EndStreamDTO implements JsonSerializable
{
    public function __construct(
        public ?StreamDTO $udp,
        public ?StreamDTO $sender,
        public ?StreamDTO $receiver,
    ) {}

    /** @return array{udp: ?StreamDTO, sender: ?StreamDTO, receiver: ?StreamDTO} */
    public function jsonSerialize(): array
    {
        return [
            'udp' => $this->udp,
            'sender' => $this->sender,
            'receiver' => $this->receiver,
        ];
    }
}
