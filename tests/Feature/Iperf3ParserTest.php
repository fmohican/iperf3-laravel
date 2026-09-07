<?php

declare(strict_types=1);

namespace fmohican\Iperf3Laravel\Tests\Feature;

use fmohican\Iperf3Laravel\Data\Iperf3Result;
use fmohican\Iperf3Laravel\Data\TCP\IntervalDTO as TcpIntervalDTO;
use fmohican\Iperf3Laravel\Data\UDP\IntervalDTO as UdpIntervalDTO;
use fmohican\Iperf3Laravel\Facades\Iperf3;
use fmohican\Iperf3Laravel\Iperf3Parser;
use fmohican\Iperf3Laravel\Tests\TestCase;

final class Iperf3ParserTest extends TestCase
{
    public function test_it_parses_a_complete_tcp_file_through_the_facade(): void
    {
        $result = Iperf3::parseFile($this->fixture('tcp.json'));
        $summary = $result->summary();

        self::assertInstanceOf(Iperf3Result::class, $result);
        self::assertSame('TCP', $summary->protocol);
        self::assertContainsOnlyInstancesOf(TcpIntervalDTO::class, $result->intervals);
        self::assertSame(950.0, $summary->uploadSpeedMbps);
        self::assertSame(940.0, $summary->downloadSpeedMbps);
        self::assertSame(3, $summary->retransmits);
        self::assertSame(1.0, $summary->averageRttMs);
        self::assertSame(12.5, $result->end->cpuUtilization->hostTotal);
    }

    public function test_it_parses_udp_json_and_exposes_loss_and_jitter(): void
    {
        $json = file_get_contents($this->fixture('udp.json'));
        self::assertIsString($json);

        $result = Iperf3::parseJson($json);
        $summary = $result->summary();

        self::assertSame('UDP', $summary->protocol);
        self::assertContainsOnlyInstancesOf(UdpIntervalDTO::class, $result->intervals);
        self::assertSame(100.0, $summary->uploadSpeedMbps);
        self::assertSame(0.04, $summary->jitterMs);
        self::assertSame(1, $summary->lostPackets);
        self::assertSame(1000, $summary->packets);
        self::assertSame(0.1, $summary->lostPacketsPercent);
    }

    public function test_it_resolves_the_parser_alias_from_the_laravel_container(): void
    {
        self::assertSame(
            $this->app->make(Iperf3Parser::class),
            $this->app->make('iperf3'),
        );
    }

    private function fixture(string $name): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Fixtures' . DIRECTORY_SEPARATOR . $name;
    }
}
