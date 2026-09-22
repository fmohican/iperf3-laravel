<?php

declare(strict_types=1);

namespace fmohican\Iperf3Laravel\Tests\Unit;

use fmohican\Iperf3Laravel\Exceptions\Iperf3ParseException;
use fmohican\Iperf3Laravel\Iperf3Parser;
use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class Iperf3ParserSecurityTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    public function test_json_exactly_at_the_limit_is_accepted(): void
    {
        $json = $this->fixtureJson('tcp.json');

        $result = (new Iperf3Parser(strlen($json)))->parseJson($json);

        self::assertSame('TCP', $result->summary()->protocol);
    }

    public function test_json_one_byte_above_the_limit_is_rejected(): void
    {
        $json = $this->fixtureJson('tcp.json');

        $this->expectException(Iperf3ParseException::class);
        $this->expectExceptionMessage(sprintf('%d-byte limit', strlen($json) - 1));

        (new Iperf3Parser(strlen($json) - 1))->parseJson($json);
    }

    public function test_a_very_small_limit_is_still_enforced(): void
    {
        $this->expectException(Iperf3ParseException::class);
        $this->expectExceptionMessage('1-byte limit');

        (new Iperf3Parser(1))->parseJson('{}');
    }

    #[DataProvider('invalidLimitProvider')]
    public function test_non_positive_size_limits_are_rejected(int $limit): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('positive integer');

        new Iperf3Parser($limit);
    }

    /** @return iterable<string, array{int}> */
    public static function invalidLimitProvider(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
        yield 'minimum integer' => [PHP_INT_MIN];
    }

    public function test_the_maximum_integer_limit_does_not_overflow_stream_arithmetic(): void
    {
        $result = (new Iperf3Parser(PHP_INT_MAX))->parseJson($this->fixtureJson('tcp.json'));

        self::assertSame('TCP', $result->summary()->protocol);
    }

    public function test_stream_exactly_at_the_limit_is_accepted_and_left_open(): void
    {
        $json = $this->fixtureJson('tcp.json');
        $stream = $this->memoryStream($json);

        try {
            $result = (new Iperf3Parser(strlen($json)))->parseStream($stream);

            self::assertSame('TCP', $result->summary()->protocol);
            self::assertIsResource($stream);
        } finally {
            fclose($stream);
        }
    }

    public function test_oversized_stream_stops_after_the_first_excess_byte_and_is_left_open(): void
    {
        $limit = 16;
        $stream = $this->memoryStream(str_repeat('x', 4096));

        try {
            (new Iperf3Parser($limit))->parseStream($stream);
            self::fail('Expected an input size exception.');
        } catch (Iperf3ParseException $exception) {
            self::assertStringContainsString('16-byte limit', $exception->getMessage());
            self::assertSame($limit + 1, ftell($stream));
            self::assertIsResource($stream);
        } finally {
            fclose($stream);
        }
    }

    public function test_file_exactly_at_the_limit_is_accepted(): void
    {
        $json = $this->fixtureJson('tcp.json');
        $file = $this->temporaryFile($json);

        $result = (new Iperf3Parser(strlen($json)))->parseFile($file);

        self::assertSame('TCP', $result->summary()->protocol);
    }

    public function test_oversized_file_is_rejected_before_json_decoding(): void
    {
        $json = $this->fixtureJson('tcp.json');
        $file = $this->temporaryFile($json . ' ');

        $this->expectException(Iperf3ParseException::class);
        $this->expectExceptionMessage(sprintf('%d-byte limit', strlen($json)));

        (new Iperf3Parser(strlen($json)))->parseFile($file);
    }

    #[DataProvider('emptyInputProvider')]
    public function test_empty_json_is_rejected_cleanly(string $json): void
    {
        $this->expectException(Iperf3ParseException::class);
        $this->expectExceptionMessage('JSON document is empty');

        (new Iperf3Parser())->parseJson($json);
    }

    /** @return iterable<string, array{string}> */
    public static function emptyInputProvider(): iterable
    {
        yield 'empty string' => [''];
        yield 'JSON whitespace' => [" \t\r\n"];
    }

    public function test_malformed_utf8_is_wrapped_in_the_domain_exception(): void
    {
        try {
            (new Iperf3Parser())->parseJson("{\"error\":\"\xB1\"}");
            self::fail('Expected malformed UTF-8 to be rejected.');
        } catch (Iperf3ParseException $exception) {
            self::assertStringContainsString('not valid JSON', $exception->getMessage());
            self::assertInstanceOf(JsonException::class, $exception->getPrevious());
        }
    }

    public function test_excessively_deep_json_is_wrapped_in_the_domain_exception(): void
    {
        $json = '{"nested":' . str_repeat('[', 513) . '0' . str_repeat(']', 513) . '}';

        try {
            (new Iperf3Parser())->parseJson($json);
            self::fail('Expected deeply nested JSON to be rejected.');
        } catch (Iperf3ParseException $exception) {
            self::assertStringContainsString('not valid JSON', $exception->getMessage());
            self::assertInstanceOf(JsonException::class, $exception->getPrevious());
        }
    }

    #[DataProvider('invalidRootProvider')]
    public function test_non_object_json_roots_are_rejected(string $json): void
    {
        $this->expectException(Iperf3ParseException::class);
        $this->expectExceptionMessage('root value must be an object');

        (new Iperf3Parser())->parseJson($json);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidRootProvider(): iterable
    {
        yield 'null' => ['null'];
        yield 'boolean' => ['true'];
        yield 'number' => ['42'];
        yield 'string' => ['"result"'];
        yield 'empty array' => ['[]'];
        yield 'populated array' => ['[{"start":{}}]'];
    }

    public function test_invalid_protocol_is_rejected_without_reflecting_its_value(): void
    {
        $payload = $this->fixturePayload('tcp.json');
        $payload['start']['test_start']['protocol'] = str_repeat('sensitive-', 200);

        try {
            (new Iperf3Parser())->parseJson($this->encode($payload));
            self::fail('Expected an invalid protocol exception.');
        } catch (Iperf3ParseException $exception) {
            self::assertStringContainsString('protocol must be TCP or UDP', $exception->getMessage());
            self::assertStringNotContainsString('sensitive-', $exception->getMessage());
        }
    }

    #[DataProvider('invalidMetricTypeProvider')]
    public function test_invalid_metric_types_are_rejected(mixed $value): void
    {
        $payload = $this->fixturePayload('tcp.json');
        $payload['end']['sum_sent']['bits_per_second'] = $value;

        $this->expectException(Iperf3ParseException::class);
        $this->expectExceptionMessage('$.end.sum_sent.bits_per_second must be numeric');

        (new Iperf3Parser())->parseJson($this->encode($payload));
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidMetricTypeProvider(): iterable
    {
        yield 'non-numeric string' => ['definitely-not-a-number'];
        yield 'array' => [[]];
        yield 'object' => [['unexpected' => 'object']];
        yield 'boolean' => [true];
        yield 'null' => [null];
    }

    public function test_integral_float_counters_from_json_are_supported(): void
    {
        $payload = $this->fixturePayload('tcp.json');
        $payload['end']['sum_sent']['bytes'] = 237_500_000.0;

        $result = (new Iperf3Parser())->parseJson($this->encode($payload));

        self::assertSame(237_500_000, $result->end->sumSent?->bytes);
    }

    #[DataProvider('invalidTcpNumberProvider')]
    public function test_invalid_tcp_numeric_values_are_rejected(string $field, int|float $value): void
    {
        $payload = $this->fixturePayload('tcp.json');
        $payload['end']['sum_sent'][$field] = $value;

        $this->expectException(Iperf3ParseException::class);
        $this->expectExceptionMessage('$.end.sum_sent.' . $field);

        (new Iperf3Parser())->parseJson($this->encode($payload));
    }

    /** @return iterable<string, array{string, int|float}> */
    public static function invalidTcpNumberProvider(): iterable
    {
        yield 'negative byte count' => ['bytes', -1];
        yield 'fractional byte count' => ['bytes', 1.5];
        yield 'negative bitrate' => ['bits_per_second', -0.1];
        yield 'negative retransmits' => ['retransmits', -1];
        yield 'negative duration' => ['seconds', -1.0];
        yield 'negative RTT' => ['mean_rtt', -1.0];
    }

    #[DataProvider('invalidUdpNumberProvider')]
    public function test_invalid_udp_numeric_values_are_rejected(string $field, int|float $value): void
    {
        $payload = $this->fixturePayload('udp.json');
        $payload['end']['sum'][$field] = $value;

        $this->expectException(Iperf3ParseException::class);
        $this->expectExceptionMessage('$.end.sum.' . $field);

        (new Iperf3Parser())->parseJson($this->encode($payload));
    }

    /** @return iterable<string, array{string, int|float}> */
    public static function invalidUdpNumberProvider(): iterable
    {
        yield 'negative packets' => ['packets', -1];
        yield 'negative lost packets' => ['lost_packets', -1];
        yield 'negative jitter' => ['jitter_ms', -0.1];
        yield 'percentage over 100' => ['lost_percent', 100.1];
    }

    public function test_lost_packets_cannot_exceed_the_packet_count(): void
    {
        $payload = $this->fixturePayload('udp.json');
        $payload['end']['sum']['lost_packets'] = 1001;

        $this->expectException(Iperf3ParseException::class);
        $this->expectExceptionMessage('lost_packets cannot exceed packets');

        (new Iperf3Parser())->parseJson($this->encode($payload));
    }

    public function test_non_finite_numeric_overflow_is_rejected(): void
    {
        $json = $this->fixtureJson('tcp.json');
        $json = str_replace(
            '"bits_per_second": 950000000',
            '"bits_per_second": 1e400',
            $json,
            $replacements,
        );
        self::assertGreaterThan(0, $replacements);

        $this->expectException(Iperf3ParseException::class);
        $this->expectExceptionMessage('bits_per_second must be finite');

        (new Iperf3Parser())->parseJson($json);
    }

    public function test_integer_overflow_is_rejected_without_coercion(): void
    {
        $json = $this->fixtureJson('tcp.json');
        $json = str_replace(
            '"bytes": 237500000',
            '"bytes": 9223372036854775808',
            $json,
            $replacements,
        );
        self::assertGreaterThan(0, $replacements);

        $this->expectException(Iperf3ParseException::class);
        $this->expectExceptionMessage('bytes must be a safely representable integer');

        (new Iperf3Parser())->parseJson($json);
    }

    public function test_iperf_error_documents_are_rejected_with_bounded_sanitized_diagnostics(): void
    {
        $json = $this->encode(['error' => "remote failure\n" . str_repeat('x', 5000)]);

        try {
            (new Iperf3Parser())->parseJson($json);
            self::fail('Expected the iperf3 error document to be rejected.');
        } catch (Iperf3ParseException $exception) {
            self::assertStringStartsWith('iperf3 reported an error: remote failure ', $exception->getMessage());
            self::assertStringContainsString('[truncated]', $exception->getMessage());
            self::assertStringNotContainsString("\n", $exception->getMessage());
            self::assertLessThan(1100, strlen($exception->getMessage()));
        }
    }

    #[DataProvider('invalidIperfErrorProvider')]
    public function test_malformed_iperf_error_documents_are_rejected(mixed $error): void
    {
        $this->expectException(Iperf3ParseException::class);
        $this->expectExceptionMessage('$.error must be a non-empty string');

        (new Iperf3Parser())->parseJson($this->encode(['error' => $error]));
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidIperfErrorProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace' => [" \t\r\n"];
        yield 'array' => [[]];
        yield 'number' => [500];
        yield 'null' => [null];
    }

    public function test_non_stream_and_closed_stream_resources_are_rejected(): void
    {
        $parser = new Iperf3Parser();

        foreach ([null, 'not-a-stream', new stdClass()] as $invalidStream) {
            try {
                $parser->parseStream($invalidStream);
                self::fail('Expected a non-stream value to be rejected.');
            } catch (Iperf3ParseException $exception) {
                self::assertStringContainsString('not an open stream resource', $exception->getMessage());
            }
        }

        $closedStream = $this->memoryStream('{}');
        fclose($closedStream);

        $this->expectException(Iperf3ParseException::class);
        $this->expectExceptionMessage('not an open stream resource');

        $parser->parseStream($closedStream);
    }

    public function test_caller_owned_stream_remains_open_after_a_parse_failure(): void
    {
        $stream = $this->memoryStream('{');

        try {
            (new Iperf3Parser())->parseStream($stream);
            self::fail('Expected malformed JSON to be rejected.');
        } catch (Iperf3ParseException) {
            self::assertIsResource($stream);
        } finally {
            fclose($stream);
        }
    }

    public function test_missing_paths_fail_without_disclosing_the_path_in_the_message(): void
    {
        $path = __DIR__ . DIRECTORY_SEPARATOR . 'sensitive-missing-result.json';

        try {
            (new Iperf3Parser())->parseFile($path);
            self::fail('Expected the missing file to be rejected.');
        } catch (Iperf3ParseException $exception) {
            self::assertStringContainsString('does not exist', $exception->getMessage());
            self::assertStringNotContainsString($path, $exception->getMessage());
            self::assertSame($path, $exception->sourcePath());
        }
    }

    public function test_directories_are_not_accepted_as_result_files(): void
    {
        $path = __DIR__;

        try {
            (new Iperf3Parser())->parseFile($path);
            self::fail('Expected the directory to be rejected.');
        } catch (Iperf3ParseException $exception) {
            self::assertStringContainsString('not a regular file', $exception->getMessage());
            self::assertStringNotContainsString($path, $exception->getMessage());
            self::assertSame($path, $exception->sourcePath());
        }
    }

    public function test_unopenable_files_fail_with_a_domain_exception_and_no_path_disclosure(): void
    {
        $scheme = 'iperf3unreadable';
        self::assertTrue(stream_wrapper_register($scheme, UnopenableFileStreamWrapper::class));
        $path = $scheme . '://sensitive-result.json';

        try {
            (new Iperf3Parser())->parseFile($path);
            self::fail('Expected the unopenable file to be rejected.');
        } catch (Iperf3ParseException $exception) {
            self::assertStringContainsString('not readable', $exception->getMessage());
            self::assertStringNotContainsString($path, $exception->getMessage());
            self::assertSame($path, $exception->sourcePath());
        } finally {
            stream_wrapper_unregister($scheme);
        }
    }

    /** @return resource */
    private function memoryStream(string $contents)
    {
        $stream = fopen('php://memory', 'r+b');
        self::assertIsResource($stream);
        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }

    private function fixtureJson(string $name): string
    {
        $json = file_get_contents(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Fixtures' . DIRECTORY_SEPARATOR . $name);
        self::assertIsString($json);

        return $json;
    }

    /** @return array<string, mixed> */
    private function fixturePayload(string $name): array
    {
        $payload = json_decode($this->fixtureJson($name), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);

        return $payload;
    }

    /** @param array<mixed> $payload */
    private function encode(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR);
    }

    private function temporaryFile(string $contents): string
    {
        $file = tempnam(sys_get_temp_dir(), 'iperf3-parser-');
        self::assertIsString($file);
        self::assertSame(strlen($contents), file_put_contents($file, $contents));
        $this->temporaryFiles[] = $file;

        return $file;
    }
}

final class UnopenableFileStreamWrapper
{
    public mixed $context;

    /** @return array{mode: int, size: int} */
    public function url_stat(string $path, int $flags): array
    {
        return ['mode' => 0100444, 'size' => 2];
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return false;
    }
}
