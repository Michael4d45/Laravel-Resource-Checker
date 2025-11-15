<?php

declare(strict_types=1);

namespace Michael4d45\LaravelResourceChecker\Providers;

use Illuminate\Support\ServiceProvider;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResourcesCommand;

class LaravelResourceCheckerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/migration-resource-checker.php' => config_path('migration-resource-checker.php'),
        ], 'config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                CheckMigrationsResourcesCommand::class,
            ]);
        }
    }
}
