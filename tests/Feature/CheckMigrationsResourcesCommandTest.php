<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

test('command runs and writes a report file', function (): void {
    /** @var TestCase $this */
    config()->set('migration-resource-checker.enabled_sections', [
        'migrations' => false,
        'resources' => false,
        'models' => false,
        'report' => true,
    ]);

    $outputPath = base_path('reports/test_migration_resource_report.json');

    if (file_exists($outputPath)) {
        unlink($outputPath);
    }

    $this->artisan('check:migrations-resources', [
        '--output' => $outputPath,
    ])->assertExitCode(0);

    expect(file_exists($outputPath))->toBeTrue();

    $json = file_get_contents($outputPath);
    expect($json)->not->toBeFalse();

    $decoded = json_decode((string) $json, true);
    expect($decoded)->toBeArray();
    expect($decoded)->toHaveKey('report');

    if (file_exists($outputPath)) {
        unlink($outputPath);
    }
});

test('json-only option outputs only report json', function (): void {
    /** @var TestCase $this */
    config()->set('migration-resource-checker.enabled_sections', [
        'migrations' => false,
        'resources' => false,
        'models' => false,
        'report' => true,
    ]);

    $outputPath = base_path('reports/test_migration_resource_report_json_only.json');

    if (file_exists($outputPath)) {
        unlink($outputPath);
    }

    $exitCode = Artisan::call('check:migrations-resources', [
        '--output' => $outputPath,
        '--json-only' => true,
    ]);

    expect($exitCode)->toBe(0);

    $consoleOutput = Artisan::output();

    expect($consoleOutput)->not->toContain('Comparing Filament resources and models against migrations using AST parsing...');
    expect($consoleOutput)->not->toContain('Report written to:');

    $decoded = json_decode($consoleOutput, true);

    expect($decoded)->toBeArray();
    expect($decoded)->toBe([]);
    expect($decoded)->not->toHaveKey('morph_to_relationships');

    if (file_exists($outputPath)) {
        unlink($outputPath);
    }
});