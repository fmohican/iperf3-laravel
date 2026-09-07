<?php

declare(strict_types=1);

namespace fmohican\Iperf3Laravel\Enums;

enum ThroughputUnit: string
{
    case BitsPerSecond = 'bps';
    case KilobitsPerSecond = 'Kbps';
    case MegabitsPerSecond = 'Mbps';
    case GigabitsPerSecond = 'Gbps';

    public function divisor(): float
    {
        return match ($this) {
            self::BitsPerSecond => 1.0,
            self::KilobitsPerSecond => 1_000.0,
            self::MegabitsPerSecond => 1_000_000.0,
            self::GigabitsPerSecond => 1_000_000_000.0,
        };
    }
}
