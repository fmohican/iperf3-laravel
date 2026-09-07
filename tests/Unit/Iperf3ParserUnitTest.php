<?php

declare(strict_types=1);

namespace fmohican\Iperf3Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use fmohican\Iperf3Laravel\Enums\ThroughputUnit;
use fmohican\Iperf3Laravel\Exceptions\Iperf3ParseException;
use fmohican\Iperf3Laravel\Iperf3Parser;

final class Iperf3ParserUnitTest extends TestCase
{
    public function test_it_parses_an_open_stream(): void
    {
        $stream = fopen($this->fixture('tcp.json'), 'rb');
        self::assertIsResource($stream);

        try {
            $result = (new Iperf3Parser())->parseStream($stream);
        } finally {
            fclose($stream);
        }

        self::assertSame('TCP', $result->summary()->protocol);
    }

    public function test_summary_unit_conversion_is_fluent_and_immutable(): void
    {
        $summary = (new Iperf3Parser())->parseFile($this->fixture('tcp.json'))->summary();
        $gbps = $summary->toGbps();

        self::assertNotSame($summary, $gbps);
        self::assertSame(ThroughputUnit::MegabitsPerSecond, $summary->unit);
        self::assertSame(ThroughputUnit::GigabitsPerSecond, $gbps->unit);
        self::assertSame(0.95, $gbps->uploadSpeed);
        self::assertSame(0.94, $gbps->downloadSpeed);
        self::assertSame(950.0, $gbps->uploadSpeedMbps);
    }

    public function test_it_rejects_malformed_json_with_the_domain_exception(): void
    {
        $this->expectException(Iperf3ParseException::class);
        $this->expectExceptionMessage('not valid JSON');

        (new Iperf3Parser())->parseJson('{"start":');
    }

    public function test_it_rejects_incomplete_payloads_with_a_precise_path(): void
    {
        $this->expectException(Iperf3ParseException::class);
        $this->expectExceptionMessage('$.intervals is required');

        (new Iperf3Parser())->parseJson('{"start": {}, "end": {}}');
    }

    public function test_it_rejects_streams_over_the_size_limit(): void
    {
        $stream = fopen('php://memory', 'r+b');
        self::assertIsResource($stream);
        fwrite($stream, str_repeat('x', 17));
        rewind($stream);

        try {
            (new Iperf3Parser(maxInputBytes: 16))->parseStream($stream);
            self::fail('Expected an input size exception.');
        } catch (Iperf3ParseException $exception) {
            self::assertStringContainsString('16-byte limit', $exception->getMessage());
        } finally {
            fclose($stream);
        }
    }

    private function fixture(string $name): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Fixtures' . DIRECTORY_SEPARATOR . $name;
    }
}
