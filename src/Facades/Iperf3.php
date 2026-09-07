<?php

declare(strict_types=1);

namespace fmohican\Iperf3Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use fmohican\Iperf3Laravel\Data\Iperf3Result;
use fmohican\Iperf3Laravel\Iperf3Parser;

/**
 * @method static Iperf3Result parseFile(string $path)
 * @method static Iperf3Result parseJson(string $json)
 * @method static Iperf3Result parseStream(mixed $stream)
 *
 * @see Iperf3Parser
 */
final class Iperf3 extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Iperf3Parser::class;
    }
}
