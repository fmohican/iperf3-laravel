<?php

declare(strict_types=1);

namespace fmohican\Iperf3Laravel\Data\TCP;

use JsonSerializable;

final readonly class IntervalDTO implements JsonSerializable
{
    /** @param list<StreamDTO> $streams */
    public function __construct(
        public array $streams,
        public StreamDTO $sum,
    ) {}

    /** @return array{streams: list<StreamDTO>, sum: StreamDTO} */
    public function jsonSerialize(): array
    {
        return ['streams' => $this->streams, 'sum' => $this->sum];
    }
}
