<?php

declare(strict_types=1);

namespace Michael4d45\LaravelResourceChecker\Providers;

use Illuminate\Support\ServiceProvider;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\Services\DocBlockTypeExtractor;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\Services\SurveyorModelAnalyzer;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResourcesCommand;

class LaravelResourceCheckerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (class_exists(\Laravel\Surveyor\SurveyorServiceProvider::class)) {
            $this->app->register(\Laravel\Surveyor\SurveyorServiceProvider::class);
        }

        $this->app->singleton(DocBlockTypeExtractor::class);
        $this->app->singleton(SurveyorModelAnalyzer::class);
        $this->app->singleton(\Laravel\Surveyor\Analyzer\Analyzer::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__
                . '/../config/migration-resource-checker.php' => config_path(
                'migration-resource-checker.php',
            ),
        ], 'config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                CheckMigrationsResourcesCommand::class,
            ]);
        }
    }
}
