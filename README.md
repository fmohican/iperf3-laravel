# iperf3-laravel

A strict, strongly typed iperf3 JSON parser for PHP 8.5 and Laravel 13. It
supports TCP and UDP results, including intervals, per-stream metrics,
throughput, retransmits, RTT, packet loss, jitter, and host/remote CPU usage.

## Installation

```bash
composer require fmohican/iperf3-laravel
```

Laravel discovers the service provider and the `Iperf3` facade automatically.
The optional input-size configuration can be published with:

```bash
php artisan vendor:publish --tag=iperf3-config
```

## Usage

```php
use fmohican\Iperf3Laravel\Facades\Iperf3;

$result = Iperf3::parseFile('/path/to/result.json');
$summary = $result->summary();

echo $summary->protocol;             // TCP or UDP
echo $summary->uploadSpeedMbps;      // sender throughput
echo $summary->downloadSpeedMbps;    // receiver throughput
echo $summary->retransmits;
echo $summary->jitterMs;
echo $summary->lostPacketsPercent;
echo $result->end->cpuUtilization->hostTotal;
```

Raw JSON and open stream resources are accepted too:

```php
$fromJson = Iperf3::parseJson($json);

$stream = fopen('/path/to/result.json', 'rb');

try {
    $fromStream = Iperf3::parseStream($stream);
} finally {
    fclose($stream);
}
```

Every returned DTO is immutable. A summary defaults to Mbps; conversion methods
return a new summary whose `uploadSpeed` and `downloadSpeed` properties use the
selected unit:

```php
$mbps = $summary->toMbps();
$gbps = $summary->toGbps();

echo $gbps->uploadSpeed;
echo $gbps->unit->value; // Gbps
```

For standard TCP output, upload maps to `end.sum_sent` and download maps to
`end.sum_received`. For a one-way UDP `end.sum`, the direction is determined by
iperf3's `reverse` flag.

## Errors and limits

Malformed JSON, incomplete result structures, unsupported protocols, invalid
streams, and iperf3 error documents throw
`fmohican\Iperf3Laravel\Exceptions\Iperf3ParseException`. File and stream input
defaults to a 64 MiB limit, configurable through `iperf3.max_input_bytes`; use
`0` to disable the guard.

## Development

```bash
composer install
composer test
composer lint
```
