<?php

declare(strict_types=1);

namespace Michael4d45\LaravelResourceChecker\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Pipeline;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\AnalysisResultDto;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\Pipes\FixAddFieldsToModelDocsPipe;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\Pipes\FixAddFieldsToResourcesPipe;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\Pipes\FixMissingPropertiesPipe;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\Pipes\FixMissingPropertyReadPipe;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\Pipes\FixWrongModelDocTypesPipe;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\Pipes\FixWrongPropertyReadPipe;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\Pipes\GenerateReportPipe;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\Pipes\ParseFilamentFormsPipe;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\Pipes\ParseMigrationsPipe;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\Pipes\ParseModelsPipe;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\Pipes\ReadDatabasePipe;

class CheckMigrationsResourcesCommand extends Command
{
    protected $signature = 'check:migrations-resources '
        . '{--output= : Output path for JSON report} '
        . '{--fix-missing-properties : Automatically add missing @property annotations to model PHPDoc} '
        . '{--fix-missing-property-read : Automatically add missing @property-read annotations for relationships to model PHPDoc} '
        . '{--fix-wrong-property-read : Automatically fix wrong @property-read property names to snake_case}'
        . '{--fix-wrong-model-doc-types : Automatically fix wrong PHPDoc property types to match migrations}'
        . '{--fix-add-fields-to-resources : Automatically add missing fields to resource form schemas}'
        . '{--fix-add-fields-to-model-docs : Automatically add missing @property annotations for fields to model PHPDoc}';

    protected $description = 'Compare Filament resources and models against migrations (PHP files) using AST parsing';

    public function handle(): int
    {
        $this->info('Comparing Filament resources and models against migrations using AST parsing...');

        // Check if database connection is available
        try {
            DB::connection()->getPdo();
            $connected = true;
        } catch (\Throwable $e) {
            $connected = false;
        }

        $pipes = [
            $connected ? ReadDatabasePipe::class : ParseMigrationsPipe::class,
            ParseFilamentFormsPipe::class,
            ParseModelsPipe::class,
            GenerateReportPipe::class,
        ];

        if ($this->option('fix-missing-properties')) {
            $pipes[] = new FixMissingPropertiesPipe($this);
        }

        if ($this->option('fix-missing-property-read')) {
            $pipes[] = new FixMissingPropertyReadPipe($this);
        }

        if ($this->option('fix-wrong-property-read')) {
            $pipes[] = new FixWrongPropertyReadPipe($this);
        }

        if ($this->option('fix-wrong-model-doc-types')) {
            $pipes[] = new FixWrongModelDocTypesPipe($this);
        }

        if ($this->option('fix-add-fields-to-resources')) {
            $pipes[] = new FixAddFieldsToResourcesPipe($this);
        }

        if ($this->option('fix-add-fields-to-model-docs')) {
            $pipes[] = new FixAddFieldsToModelDocsPipe($this);
        }

        $dto = app(AnalysisResultDto::class);

        $dto = Pipeline::send($dto)->through($pipes)->thenReturn();

        /** @var AnalysisResultDto $dto */
        $report = $dto->report->toArray();
        $fullOutput = [
            'report' => $report,
            'resources' => array_map(fn ($resource) => $resource->toArray(), $dto->resources),
        ];

        $json = json_encode($fullOutput, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        /** @var false|string $outputPathRaw */
        $outputPathRaw = $this->option('output');
        $defaultPath = 'reports/migration_resource_report.json';
        $configValue = config('migration-resource-checker.output_path');
        $configPath = is_string($configValue) ? $configValue : $defaultPath;
        $outputPath = is_string($outputPathRaw) ? $outputPathRaw : base_path($configPath);
        if (! is_dir(dirname($outputPath))) {
            @mkdir(dirname($outputPath), 0755, true);
        }
        file_put_contents($outputPath, $json . PHP_EOL);

        $this->info('Report written to: ' . $outputPath);
        $actionableJson = json_encode($report, JSON_PRETTY_PRINT);
        if (is_string($actionableJson)) {
            $this->line($actionableJson);
        }

        return self::SUCCESS;
    }
}
