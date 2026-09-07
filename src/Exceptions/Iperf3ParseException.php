<?php

declare(strict_types=1);

namespace fmohican\Iperf3Laravel\Exceptions;

use JsonException;
use RuntimeException;
use Throwable;

final class Iperf3ParseException extends RuntimeException
{
    public static function fileNotFound(string $path): self
    {
        return new self(sprintf('The iperf3 result file "%s" does not exist.', $path));
    }

    public static function fileNotReadable(string $path): self
    {
        return new self(sprintf('The iperf3 result file "%s" is not readable.', $path));
    }

    public static function invalidStream(): self
    {
        return new self('The supplied value is not an open stream resource.');
    }

    public static function streamReadFailed(): self
    {
        return new self('The iperf3 stream could not be read.');
    }

    public static function inputTooLarge(int $limit): self
    {
        return new self(sprintf('The iperf3 payload exceeds the configured %d-byte limit.', $limit));
    }

    public static function invalidJson(JsonException $exception): self
    {
        return new self('The iperf3 payload is not valid JSON: ' . $exception->getMessage(), 0, $exception);
    }

    public static function invalidPayload(string $message, ?Throwable $previous = null): self
    {
        return new self('Invalid iperf3 payload: ' . $message, 0, $previous);
    }
}
