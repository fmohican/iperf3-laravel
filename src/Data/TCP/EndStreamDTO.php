<?php

declare(strict_types=1);

namespace fmohican\Iperf3Laravel\Data\TCP;

use JsonSerializable;

final readonly class EndStreamDTO implements JsonSerializable
{
    public function __construct(
        public ?StreamDTO $sender,
        public ?StreamDTO $receiver,
    ) {}

    /** @return array{sender: ?StreamDTO, receiver: ?StreamDTO} */
    public function jsonSerialize(): array
    {
        return ['sender' => $this->sender, 'receiver' => $this->receiver];
    }
}
