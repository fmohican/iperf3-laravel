<?php

declare(strict_types=1);

namespace fmohican\Iperf3Laravel\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use fmohican\Iperf3Laravel\Iperf3ServiceProvider;

abstract class TestCase extends Orchestra
{
    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [Iperf3ServiceProvider::class];
    }
}
