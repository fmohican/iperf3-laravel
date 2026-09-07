<?php

declare(strict_types=1);

namespace fmohican\Iperf3Laravel\Enums;

enum Protocol: string
{
    case TCP = 'TCP';
    case UDP = 'UDP';
}
