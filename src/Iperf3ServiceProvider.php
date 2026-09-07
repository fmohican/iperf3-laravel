<?php

declare(strict_types=1);

namespace fmohican\Iperf3Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class Iperf3ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/iperf3.php', 'iperf3');

        $this->app->singleton(
            Iperf3Parser::class,
            static fn(Application $app): Iperf3Parser => new Iperf3Parser(
                maxInputBytes: (int) $app['config']->get('iperf3.max_input_bytes', 64 * 1024 * 1024),
            ),
        );

        $this->app->alias(Iperf3Parser::class, 'iperf3');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/iperf3.php' => $this->app->configPath('iperf3.php'),
        ], 'iperf3-config');
    }
}
