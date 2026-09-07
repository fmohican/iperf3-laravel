<?php

declare(strict_types=1);

namespace fmohican\Iperf3Laravel;

use JsonException;
use fmohican\Iperf3Laravel\Data\ConnectionDTO;
use fmohican\Iperf3Laravel\Data\CpuUtilizationDTO;
use fmohican\Iperf3Laravel\Data\EndSummaryDTO;
use fmohican\Iperf3Laravel\Data\Iperf3Result;
use fmohican\Iperf3Laravel\Data\StartDTO;
use fmohican\Iperf3Laravel\Data\TCP\EndStreamDTO as TcpEndStreamDTO;
use fmohican\Iperf3Laravel\Data\TCP\IntervalDTO as TcpIntervalDTO;
use fmohican\Iperf3Laravel\Data\TCP\StreamDTO as TcpStreamDTO;
use fmohican\Iperf3Laravel\Data\UDP\EndStreamDTO as UdpEndStreamDTO;
use fmohican\Iperf3Laravel\Data\UDP\IntervalDTO as UdpIntervalDTO;
use fmohican\Iperf3Laravel\Data\UDP\StreamDTO as UdpStreamDTO;
use fmohican\Iperf3Laravel\Enums\Protocol;
use fmohican\Iperf3Laravel\Exceptions\Iperf3ParseException;

final readonly class Iperf3Parser
{
    private const int READ_CHUNK_BYTES = 1024 * 1024;

    public function __construct(private int $maxInputBytes = 64 * 1024 * 1024)
    {
        if ($this->maxInputBytes < 0) {
            throw new \InvalidArgumentException('The maximum input size cannot be negative.');
        }
    }

    public function parseFile(string $path): Iperf3Result
    {
        if (! is_file($path)) {
            throw Iperf3ParseException::fileNotFound($path);
        }

        if (! is_readable($path)) {
            throw Iperf3ParseException::fileNotReadable($path);
        }

        $size = filesize($path);

        if ($size !== false && $this->maxInputBytes > 0 && $size > $this->maxInputBytes) {
            throw Iperf3ParseException::inputTooLarge($this->maxInputBytes);
        }

        $stream = fopen($path, 'rb');

        if ($stream === false) {
            throw Iperf3ParseException::fileNotReadable($path);
        }

        try {
            return $this->parseStream($stream);
        } finally {
            fclose($stream);
        }
    }

    public function parseJson(string $json): Iperf3Result
    {
        if ($json === '' || trim($json) === '') {
            throw Iperf3ParseException::invalidPayload('the JSON document is empty.');
        }

        if ($this->maxInputBytes > 0 && strlen($json) > $this->maxInputBytes) {
            throw Iperf3ParseException::inputTooLarge($this->maxInputBytes);
        }

        try {
            $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw Iperf3ParseException::invalidJson($exception);
        }

        if (! is_array($payload)) {
            throw Iperf3ParseException::invalidPayload('the root value must be an object.');
        }

        return $this->hydrate($payload);
    }

    public function parseStream(mixed $stream): Iperf3Result
    {
        if (! is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw Iperf3ParseException::invalidStream();
        }

        $json = '';

        while (! feof($stream)) {
            $remaining = $this->maxInputBytes > 0
                ? ($this->maxInputBytes - strlen($json)) + 1
                : self::READ_CHUNK_BYTES;
            $length = min(self::READ_CHUNK_BYTES, max(1, $remaining));
            $chunk = fread($stream, $length);

            if ($chunk === false) {
                throw Iperf3ParseException::streamReadFailed();
            }

            if ($chunk === '' && ! feof($stream)) {
                throw Iperf3ParseException::streamReadFailed();
            }

            $json .= $chunk;

            if ($this->maxInputBytes > 0 && strlen($json) > $this->maxInputBytes) {
                throw Iperf3ParseException::inputTooLarge($this->maxInputBytes);
            }
        }

        return $this->parseJson($json);
    }

    /** @param array<string, mixed> $payload */
    private function hydrate(array $payload): Iperf3Result
    {
        if (isset($payload['error']) && is_string($payload['error'])) {
            throw Iperf3ParseException::invalidPayload('iperf3 reported an error: ' . $payload['error']);
        }

        $startRaw = $this->requiredArray($payload, 'start', '$');
        $intervalsRaw = $this->requiredArray($payload, 'intervals', '$');
        $endRaw = $this->requiredArray($payload, 'end', '$');
        $start = $this->hydrateStart($startRaw);

        if (! array_is_list($intervalsRaw)) {
            throw Iperf3ParseException::invalidPayload('$.intervals must be a JSON array.');
        }

        $intervals = [];

        foreach ($intervalsRaw as $index => $intervalRaw) {
            if (! is_array($intervalRaw)) {
                throw Iperf3ParseException::invalidPayload("$.intervals[{$index}] must be an object.");
            }

            $intervals[] = match ($start->protocol) {
                Protocol::TCP => $this->hydrateTcpInterval($intervalRaw, $index),
                Protocol::UDP => $this->hydrateUdpInterval($intervalRaw, $index),
            };
        }

        return new Iperf3Result(
            start: $start,
            intervals: $intervals,
            end: $this->hydrateEnd($endRaw, $start->protocol),
        );
    }

    /** @param array<string, mixed> $raw */
    private function hydrateStart(array $raw): StartDTO
    {
        $test = $this->requiredArray($raw, 'test_start', '$.start');
        $protocolValue = strtoupper($this->requiredString($test, 'protocol', '$.start.test_start'));
        $protocol = Protocol::tryFrom($protocolValue);

        if ($protocol === null) {
            throw Iperf3ParseException::invalidPayload(
                "$.start.test_start.protocol must be TCP or UDP; {$protocolValue} given.",
            );
        }

        $connectionsRaw = $this->optionalArray($raw, 'connected', '$.start') ?? [];
        $connections = [];

        if (! array_is_list($connectionsRaw)) {
            throw Iperf3ParseException::invalidPayload('$.start.connected must be a JSON array.');
        }

        foreach ($connectionsRaw as $index => $connection) {
            if (! is_array($connection)) {
                throw Iperf3ParseException::invalidPayload("$.start.connected[{$index}] must be an object.");
            }

            $connections[] = new ConnectionDTO(
                socket: $this->integer($connection, 'socket', "$.start.connected[{$index}]", 0),
                localHost: $this->string($connection, 'local_host', "$.start.connected[{$index}]", ''),
                localPort: $this->integer($connection, 'local_port', "$.start.connected[{$index}]", 0),
                remoteHost: $this->string($connection, 'remote_host', "$.start.connected[{$index}]", ''),
                remotePort: $this->integer($connection, 'remote_port', "$.start.connected[{$index}]", 0),
            );
        }

        $timestamp = $this->optionalArray($raw, 'timestamp', '$.start') ?? [];
        $target = $this->optionalArray($raw, 'connecting_to', '$.start') ?? [];
        $fallbackConnection = $connections[0] ?? null;

        return new StartDTO(
            version: $this->string($raw, 'version', '$.start', ''),
            systemInfo: $this->string($raw, 'system_info', '$.start', ''),
            timestamp: $this->nullableString($timestamp, 'time', '$.start.timestamp'),
            timestampSeconds: $this->nullableInteger($timestamp, 'timesecs', '$.start.timestamp'),
            targetHost: $this->string(
                $target,
                'host',
                '$.start.connecting_to',
                $fallbackConnection?->remoteHost ?? '',
            ),
            targetPort: $this->integer(
                $target,
                'port',
                '$.start.connecting_to',
                $fallbackConnection?->remotePort ?? 0,
            ),
            protocol: $protocol,
            numberOfStreams: $this->integer($test, 'num_streams', '$.start.test_start', 1),
            durationSeconds: $this->number($test, 'duration', '$.start.test_start'),
            intervalSeconds: $this->number($test, 'interval', '$.start.test_start', 1.0),
            reverse: $this->boolean($test, 'reverse', '$.start.test_start', false),
            bidirectional: $this->boolean($test, 'bidir', '$.start.test_start', false),
            connections: $connections,
        );
    }

    /** @param array<string, mixed> $raw */
    private function hydrateTcpInterval(array $raw, int $index): TcpIntervalDTO
    {
        $path = "$.intervals[{$index}]";
        $streamsRaw = $this->requiredArray($raw, 'streams', $path);
        $sumRaw = $this->requiredArray($raw, 'sum', $path);
        $streams = [];

        if (! array_is_list($streamsRaw)) {
            throw Iperf3ParseException::invalidPayload("{$path}.streams must be a JSON array.");
        }

        foreach ($streamsRaw as $streamIndex => $streamRaw) {
            if (! is_array($streamRaw)) {
                throw Iperf3ParseException::invalidPayload("{$path}.streams[{$streamIndex}] must be an object.");
            }

            $streams[] = $this->hydrateTcpStream($streamRaw, "{$path}.streams[{$streamIndex}]");
        }

        return new TcpIntervalDTO(
            streams: $streams,
            sum: $this->hydrateTcpStream($sumRaw, "{$path}.sum"),
        );
    }

    /** @param array<string, mixed> $raw */
    private function hydrateUdpInterval(array $raw, int $index): UdpIntervalDTO
    {
        $path = "$.intervals[{$index}]";
        $streamsRaw = $this->requiredArray($raw, 'streams', $path);
        $sumRaw = $this->requiredArray($raw, 'sum', $path);
        $streams = [];

        if (! array_is_list($streamsRaw)) {
            throw Iperf3ParseException::invalidPayload("{$path}.streams must be a JSON array.");
        }

        foreach ($streamsRaw as $streamIndex => $streamRaw) {
            if (! is_array($streamRaw)) {
                throw Iperf3ParseException::invalidPayload("{$path}.streams[{$streamIndex}] must be an object.");
            }

            $streams[] = $this->hydrateUdpStream($streamRaw, "{$path}.streams[{$streamIndex}]");
        }

        return new UdpIntervalDTO(
            streams: $streams,
            sum: $this->hydrateUdpStream($sumRaw, "{$path}.sum"),
        );
    }

    /** @param array<string, mixed> $raw */
    private function hydrateEnd(array $raw, Protocol $protocol): EndSummaryDTO
    {
        $streamsRaw = $this->optionalArray($raw, 'streams', '$.end') ?? [];
        $streams = [];

        if (! array_is_list($streamsRaw)) {
            throw Iperf3ParseException::invalidPayload('$.end.streams must be a JSON array.');
        }

        foreach ($streamsRaw as $index => $streamRaw) {
            if (! is_array($streamRaw)) {
                throw Iperf3ParseException::invalidPayload("$.end.streams[{$index}] must be an object.");
            }

            $streams[] = match ($protocol) {
                Protocol::TCP => $this->hydrateTcpEndStream($streamRaw, $index),
                Protocol::UDP => $this->hydrateUdpEndStream($streamRaw, $index),
            };
        }

        $mapper = match ($protocol) {
            Protocol::TCP => $this->hydrateTcpStream(...),
            Protocol::UDP => $this->hydrateUdpStream(...),
        };

        $sumSentRaw = $this->optionalArray($raw, 'sum_sent', '$.end');
        $sumReceivedRaw = $this->optionalArray($raw, 'sum_received', '$.end');
        $sumRaw = $this->optionalArray($raw, 'sum', '$.end');

        if ($sumSentRaw === null && $sumReceivedRaw === null && $sumRaw === null) {
            throw Iperf3ParseException::invalidPayload(
                '$.end must contain sum, sum_sent, or sum_received metrics.',
            );
        }

        return new EndSummaryDTO(
            streams: $streams,
            sumSent: $sumSentRaw === null ? null : $mapper($sumSentRaw, '$.end.sum_sent'),
            sumReceived: $sumReceivedRaw === null ? null : $mapper($sumReceivedRaw, '$.end.sum_received'),
            sum: $sumRaw === null ? null : $mapper($sumRaw, '$.end.sum'),
            cpuUtilization: $this->hydrateCpu($this->optionalArray($raw, 'cpu_utilization_percent', '$.end') ?? []),
        );
    }

    /** @param array<string, mixed> $raw */
    private function hydrateTcpEndStream(array $raw, int $index): TcpEndStreamDTO
    {
        $path = "$.end.streams[{$index}]";
        $sender = $this->optionalArray($raw, 'sender', $path);
        $receiver = $this->optionalArray($raw, 'receiver', $path);

        if ($sender === null && $receiver === null) {
            throw Iperf3ParseException::invalidPayload("{$path} must contain sender or receiver metrics.");
        }

        return new TcpEndStreamDTO(
            sender: $sender === null ? null : $this->hydrateTcpStream($sender, "{$path}.sender"),
            receiver: $receiver === null ? null : $this->hydrateTcpStream($receiver, "{$path}.receiver"),
        );
    }

    /** @param array<string, mixed> $raw */
    private function hydrateUdpEndStream(array $raw, int $index): UdpEndStreamDTO
    {
        $path = "$.end.streams[{$index}]";
        $udp = $this->optionalArray($raw, 'udp', $path);
        $sender = $this->optionalArray($raw, 'sender', $path);
        $receiver = $this->optionalArray($raw, 'receiver', $path);

        if ($udp === null && $sender === null && $receiver === null) {
            throw Iperf3ParseException::invalidPayload("{$path} must contain UDP stream metrics.");
        }

        return new UdpEndStreamDTO(
            udp: $udp === null ? null : $this->hydrateUdpStream($udp, "{$path}.udp"),
            sender: $sender === null ? null : $this->hydrateUdpStream($sender, "{$path}.sender"),
            receiver: $receiver === null ? null : $this->hydrateUdpStream($receiver, "{$path}.receiver"),
        );
    }

    /** @param array<string, mixed> $raw */
    private function hydrateTcpStream(array $raw, string $path): TcpStreamDTO
    {
        return new TcpStreamDTO(
            socket: $this->integer($raw, 'socket', $path, 0),
            startSeconds: $this->number($raw, 'start', $path, 0.0),
            endSeconds: $this->number($raw, 'end', $path, 0.0),
            seconds: $this->number($raw, 'seconds', $path),
            bytes: $this->integer($raw, 'bytes', $path),
            bitsPerSecond: $this->number($raw, 'bits_per_second', $path),
            omitted: $this->boolean($raw, 'omitted', $path, false),
            sender: $this->boolean($raw, 'sender', $path, false),
            retransmits: $this->integer($raw, 'retransmits', $path, 0),
            congestionWindowBytes: $this->nullableInteger($raw, 'snd_cwnd', $path),
            rttMicroseconds: $this->nullableNumber($raw, 'rtt', $path),
            rttVarianceMicroseconds: $this->nullableNumber($raw, 'rttvar', $path),
            pathMtuBytes: $this->nullableInteger($raw, 'pmtu', $path),
            meanRttMicroseconds: $this->nullableNumber($raw, 'mean_rtt', $path),
            minimumRttMicroseconds: $this->nullableNumber($raw, 'min_rtt', $path),
            maximumRttMicroseconds: $this->nullableNumber($raw, 'max_rtt', $path),
        );
    }

    /** @param array<string, mixed> $raw */
    private function hydrateUdpStream(array $raw, string $path): UdpStreamDTO
    {
        $packets = $this->integer($raw, 'packets', $path, 0);
        $lostPackets = $this->integer($raw, 'lost_packets', $path, 0);
        $lostPercent = $this->nullableNumber($raw, 'lost_percent', $path)
            ?? ($packets > 0 ? ($lostPackets / $packets) * 100.0 : 0.0);

        return new UdpStreamDTO(
            socket: $this->integer($raw, 'socket', $path, 0),
            startSeconds: $this->number($raw, 'start', $path, 0.0),
            endSeconds: $this->number($raw, 'end', $path, 0.0),
            seconds: $this->number($raw, 'seconds', $path),
            bytes: $this->integer($raw, 'bytes', $path),
            bitsPerSecond: $this->number($raw, 'bits_per_second', $path),
            omitted: $this->boolean($raw, 'omitted', $path, false),
            sender: $this->boolean($raw, 'sender', $path, false),
            jitterMs: $this->number($raw, 'jitter_ms', $path, 0.0),
            lostPackets: $lostPackets,
            packets: $packets,
            lostPacketsPercent: $lostPercent,
            outOfOrderPackets: $this->integer($raw, 'out_of_order', $path, 0),
        );
    }

    /** @param array<string, mixed> $raw */
    private function hydrateCpu(array $raw): CpuUtilizationDTO
    {
        return new CpuUtilizationDTO(
            hostTotal: $this->number($raw, 'host_total', '$.end.cpu_utilization_percent', 0.0),
            hostUser: $this->number($raw, 'host_user', '$.end.cpu_utilization_percent', 0.0),
            hostSystem: $this->number($raw, 'host_system', '$.end.cpu_utilization_percent', 0.0),
            remoteTotal: $this->number($raw, 'remote_total', '$.end.cpu_utilization_percent', 0.0),
            remoteUser: $this->number($raw, 'remote_user', '$.end.cpu_utilization_percent', 0.0),
            remoteSystem: $this->number($raw, 'remote_system', '$.end.cpu_utilization_percent', 0.0),
        );
    }

    /** @param array<string, mixed> $source @return array<mixed> */
    private function requiredArray(array $source, string $key, string $path): array
    {
        if (! array_key_exists($key, $source)) {
            throw Iperf3ParseException::invalidPayload("{$path}.{$key} is required.");
        }

        if (! is_array($source[$key])) {
            throw Iperf3ParseException::invalidPayload("{$path}.{$key} must be an array or object.");
        }

        return $source[$key];
    }

    /** @param array<string, mixed> $source @return array<mixed>|null */
    private function optionalArray(array $source, string $key, string $path): ?array
    {
        if (! array_key_exists($key, $source)) {
            return null;
        }

        if (! is_array($source[$key])) {
            throw Iperf3ParseException::invalidPayload("{$path}.{$key} must be an array or object.");
        }

        return $source[$key];
    }

    /** @param array<string, mixed> $source */
    private function requiredString(array $source, string $key, string $path): string
    {
        if (! isset($source[$key]) || ! is_string($source[$key]) || $source[$key] === '') {
            throw Iperf3ParseException::invalidPayload("{$path}.{$key} must be a non-empty string.");
        }

        return $source[$key];
    }

    /** @param array<string, mixed> $source */
    private function string(array $source, string $key, string $path, string $default): string
    {
        if (! array_key_exists($key, $source)) {
            return $default;
        }

        if (! is_string($source[$key])) {
            throw Iperf3ParseException::invalidPayload("{$path}.{$key} must be a string.");
        }

        return $source[$key];
    }

    /** @param array<string, mixed> $source */
    private function nullableString(array $source, string $key, string $path): ?string
    {
        return array_key_exists($key, $source) ? $this->string($source, $key, $path, '') : null;
    }

    /** @param array<string, mixed> $source */
    private function number(array $source, string $key, string $path, ?float $default = null): float
    {
        if (! array_key_exists($key, $source)) {
            if ($default !== null) {
                return $default;
            }

            throw Iperf3ParseException::invalidPayload("{$path}.{$key} is required.");
        }

        if (! is_int($source[$key]) && ! is_float($source[$key])) {
            throw Iperf3ParseException::invalidPayload("{$path}.{$key} must be numeric.");
        }

        return (float) $source[$key];
    }

    /** @param array<string, mixed> $source */
    private function nullableNumber(array $source, string $key, string $path): ?float
    {
        return array_key_exists($key, $source) ? $this->number($source, $key, $path) : null;
    }

    /** @param array<string, mixed> $source */
    private function integer(array $source, string $key, string $path, ?int $default = null): int
    {
        if (! array_key_exists($key, $source)) {
            if ($default !== null) {
                return $default;
            }

            throw Iperf3ParseException::invalidPayload("{$path}.{$key} is required.");
        }

        if (! is_int($source[$key])) {
            throw Iperf3ParseException::invalidPayload("{$path}.{$key} must be an integer.");
        }

        return $source[$key];
    }

    /** @param array<string, mixed> $source */
    private function nullableInteger(array $source, string $key, string $path): ?int
    {
        return array_key_exists($key, $source) ? $this->integer($source, $key, $path) : null;
    }

    /** @param array<string, mixed> $source */
    private function boolean(array $source, string $key, string $path, bool $default): bool
    {
        if (! array_key_exists($key, $source)) {
            return $default;
        }

        if (is_bool($source[$key])) {
            return $source[$key];
        }

        if ($source[$key] === 0 || $source[$key] === 1) {
            return (bool) $source[$key];
        }

        throw Iperf3ParseException::invalidPayload("{$path}.{$key} must be boolean.");
    }
}
