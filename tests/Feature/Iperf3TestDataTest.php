<?php

declare(strict_types=1);

namespace fmohican\Iperf3Laravel\Tests\Feature;

use fmohican\Iperf3Laravel\Data\TCP\IntervalDTO as TcpIntervalDTO;
use fmohican\Iperf3Laravel\Data\UDP\IntervalDTO as UdpIntervalDTO;
use fmohican\Iperf3Laravel\Exceptions\Iperf3ParseException;
use fmohican\Iperf3Laravel\Facades\Iperf3;
use fmohican\Iperf3Laravel\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class Iperf3TestDataTest extends TestCase
{
    /**
     * @param  class-string<TcpIntervalDTO|UdpIntervalDTO>  $intervalType
     */
    #[DataProvider('completedRunProvider')]
    public function test_it_parses_completed_real_world_runs(
        string $file,
        string $protocol,
        bool $reverse,
        string $intervalType,
        int $numberOfStreams,
        float $durationSeconds,
        float $uploadMbps,
        float $downloadMbps,
        int $packets,
        ?float $jitterMs,
    ): void {
        $result = Iperf3::parseFile($this->testData($file));
        $summary = $result->summary();

        self::assertSame($protocol, $summary->protocol);
        self::assertSame($reverse, $result->start->reverse);
        self::assertSame($numberOfStreams, $result->start->numberOfStreams);
        self::assertCount($numberOfStreams, $result->start->connections);
        self::assertCount(30, $result->intervals);
        self::assertContainsOnlyInstancesOf($intervalType, $result->intervals);
        self::assertCount($numberOfStreams, $result->end->streams);
        self::assertEqualsWithDelta($durationSeconds, $summary->durationSeconds, 0.000001);
        self::assertEqualsWithDelta($uploadMbps, $summary->uploadSpeedMbps, 0.000001);
        self::assertEqualsWithDelta($downloadMbps, $summary->downloadSpeedMbps, 0.000001);
        self::assertSame(0, $summary->lostPackets);
        self::assertSame($packets, $summary->packets);
        self::assertSame(0.0, $summary->lostPacketsPercent);

        if ($jitterMs === null) {
            self::assertNull($summary->jitterMs);
        } else {
            self::assertEqualsWithDelta($jitterMs, $summary->jitterMs, 0.000000001);
        }

        $serialized = json_decode(json_encode($summary, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);

        self::assertIsArray($serialized);
        self::assertSame($protocol, $serialized['protocol']);
    }

    public function test_it_rejects_a_real_world_failed_run(): void
    {
        $this->expectException(Iperf3ParseException::class);
        $this->expectExceptionMessage(
            'iperf3 reported an error: unable to read from stream socket: Resource temporarily unavailable',
        );

        Iperf3::parseFile($this->testData('udp_parallel_1gbps_invalid.json'));
    }

    /**
     * @return iterable<string, array{
     *     string,
     *     string,
     *     bool,
     *     class-string<TcpIntervalDTO|UdpIntervalDTO>,
     *     int,
     *     float,
     *     float,
     *     float,
     *     int,
     *     float|null
     * }>
     */
    public static function completedRunProvider(): iterable
    {
        yield 'TCP standard' => [
            'tcp_standard.json',
            'TCP',
            false,
            TcpIntervalDTO::class,
            1,
            30.013264,
            26_253.528027278237,
            26_253.368826262948,
            0,
            null,
        ];

        yield 'TCP reverse' => [
            'tcp_reverse.json',
            'TCP',
            true,
            TcpIntervalDTO::class,
            1,
            30.003569,
            24_009.60396251526,
            24_009.77385825277,
            0,
            null,
        ];

        yield 'UDP standard' => [
            'udp_standard.json',
            'UDP',
            false,
            UdpIntervalDTO::class,
            1,
            30.012292,
            1.064992457615719,
            1.0649489882345542,
            61,
            0.024211428403284243,
        ];

        yield 'UDP reverse' => [
            'udp_reverse.json',
            'UDP',
            true,
            UdpIntervalDTO::class,
            1,
            30.007571,
            1.0650769580514121,
            1.0651165334241817,
            61,
            0.02871648661418806,
        ];

        yield 'TCP parallel' => [
            'tcp_parallel.json',
            'TCP',
            false,
            TcpIntervalDTO::class,
            32,
            30.019322,
            71_055.27312967677,
            71_043.0476541742,
            0,
            null,
        ];
    }

    private function testData(string $name): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'TestData' . DIRECTORY_SEPARATOR . $name;
    }
}
