Act as a Principal PHP Architect specializing in Laravel package development.

Create a high-performance, PSR-compliant Laravel 13 package named `iperf3-laravel` (namespace: `fmohican\Iperf3Laravel`) that parses, validates, and summarizes `iperf3` JSON output files (`result.json`).

### 🛠 TECHNICAL REQUIREMENTS & CONSTRAINTS

1. PHP & Laravel Compatibility:
   - Target PHP Version: PHP 8.5+ (Utilize PHP 8.5/8.4 features like property hooks, typed properties, readonly classes, `match` expressions, and first-class callables).
   - Target Framework: Laravel 13.x (compatible with modern illuminate contracts and service providers).
   - Code Style: Strictly adhere to PSR-1, PSR-4, PSR-12, and PER CS 2.0 rules.

2. Architecture & Design Patterns:
   - Facade: Include a Facade `Iperf3` aliased to `Iperf3Parser`.
   - Data Transfer Objects (DTOs): Return strongly-typed, immutable Data Transfer Objects (`Iperf3Result`, `SummaryDTO`, `IntervalDTO`, `EndSummaryDTO`, etc.) rather than plain array structures or generic stdClasses.
   - Parsing Efficiency: Must be optimized for memory efficiency and high speed.
   - Design Aesthetic: Hand-written quality—clean abstractions, single responsibility principle (SRP), strict type declarations (`declare(strict_types=1);`), no spaghetti code or monolithic functions.

3. Core Functional Requirements:
   - Accepts a JSON string, a file path, or a stream containing `iperf3` raw output (`result.json`).
   - Parses TCP and UDP test outputs (handling lost packets, jitter, retransmits, throughput, CPU utilization, and stream summaries).
   - Generates a fast High-Level Summary via `$result->summary()` returning a `SummaryDTO` containing normalized values (e.g., bits per second converted to Mbps/Gbps, packet loss percentage, average RTT).
   - Fluently supports unit conversions (e.g., `$summary->toMbps()`, `$summary->toGbps()`).

4. Testing:
   - Include full End-to-End (E2E) and Unit tests using Pest PHP v3 or PHPUnit 11+.
   - Include mock `result.json` stubs covering both TCP and UDP scenarios.

---

### 📂 FILE STRUCTURE TO GENERATE

Please generate all files completely without skipping implementation steps:

1. `composer.json` (Defining dependencies, PSR-4 autoloading, Laravel package auto-discovery).
2. `src/Iperf3ServiceProvider.php` (Package registration & boot logic).
3. `src/Facades/Iperf3.php` (Laravel Facade implementation).
4. `src/Iperf3Parser.php` (Main entry point / Service bound to container).
5. `src/Data/Iperf3Result.php` (Root immutable DTO containing all data and summary methods).
6. `src/Data/SummaryDTO.php` (Normalized human-friendly summary object).
7. `src/Data/TCP/` & `src/Data/UDP/` sub-DTOs (Handling interval metrics and stream metrics).
8. `src/Exceptions/Iperf3ParseException.php` (Custom exception for invalid or incomplete JSON files).
9. `tests/TestCase.php` (Orchestra Testbench base setup).
10. `tests/Feature/Iperf3ParserTest.php` (E2E Pest/PHPUnit tests parsing complete JSON payloads).

---

### 💡 API DESIGN EXAMPLE (HOW THE PACKAGE WILL BE USED)

```php
use fmohican\Iperf3Laravel\Facades\Iperf3;

// Method 1: Using the Facade via file path
$result = Iperf3::parseFile('/path/to/result.json');

// Method 2: Parsing raw JSON content directly
$result = Iperf3::parseJson($jsonContent);

// Method 3: Accessing the summary object directly
$summary =$result->summary();

echo $summary->protocol;            // 'TCP' or 'UDP'
echo $summary->downloadSpeedMbps;   // e.g., 940.50
echo $summary->uploadSpeedMbps;     // e.g., 920.10
echo $summary->retransmits;         // e.g., 12
echo $summary->jitterMs;            // e.g., 0.04 (for UDP)
echo $summary->lostPacketsPercent;  // e.g., 0.1%

// Raw structured metrics are also accessible via object methods:
$cpuUsage =$result->end->cpuUtilization;
