<?php

declare(strict_types=1);

namespace fmohican\Iperf3Laravel\Exceptions;

use JsonException;
use RuntimeException;
use Throwable;

final class Iperf3ParseException extends RuntimeException
{
    private const int MAX_IPERF_ERROR_BYTES = 1024;

    private ?string $sourcePath = null;

    public static function fileNotFound(string $path): self
    {
        return self::forPath('The iperf3 result file does not exist.', $path);
    }

    public static function pathNotRegularFile(string $path): self
    {
        return self::forPath('The iperf3 result path is not a regular file.', $path);
    }

    public static function fileNotReadable(string $path): self
    {
        return self::forPath('The iperf3 result file is not readable.', $path);
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

    public static function iperfError(string $message): self
    {
        $truncated = strlen($message) > self::MAX_IPERF_ERROR_BYTES;

        if ($truncated) {
            $message = substr($message, 0, self::MAX_IPERF_ERROR_BYTES);
        }

        $message = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $message) ?? '';
        $message = trim($message);

        if ($truncated) {
            while ($message !== '' && preg_match('//u', $message) !== 1) {
                $message = substr($message, 0, -1);
            }

            $message .= '... [truncated]';
        }

        if ($message === '') {
            $message = 'unspecified failure';
        }

        return new self('iperf3 reported an error: ' . $message);
    }

    public static function invalidPayload(string $message, ?Throwable $previous = null): self
    {
        return new self('Invalid iperf3 payload: ' . $message, 0, $previous);
    }

    /**
     * The path is deliberately excluded from the exception message to reduce
     * accidental disclosure through logs or HTTP error responses.
     */
    public function sourcePath(): ?string
    {
        return $this->sourcePath;
    }

    private static function forPath(string $message, string $path): self
    {
        $exception = new self($message);
        $exception->sourcePath = $path;

        return $exception;
    }
}
