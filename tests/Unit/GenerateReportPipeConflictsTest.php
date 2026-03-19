<?php

declare(strict_types=1);

use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\AnalysisResultDto;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\FieldDto;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\FieldTable;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\ModelFieldDto;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\ModelFieldTable;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\PhpDocFieldDto;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\PhpDocFieldTable;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\ReportDto;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\RelationshipFieldDto;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\RelationshipFieldTable;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\ResourceReportDto;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\Pipes\GenerateReportPipe;

test('generate report includes model evidence conflicts', function (): void {
    $resource = new ResourceReportDto(
        modelFields: new ModelFieldTable([
            'payload' => new ModelFieldDto('payload', cast: 'json'),
        ]),
        phpdocFields: new PhpDocFieldTable([
            'payload' => new PhpDocFieldDto(
                name: 'payload',
                type: 'string',
                nullable: false,
            ),
        ]),
    );

    $dto = new AnalysisResultDto(
        report: new ReportDto,
        resources: [
            'posts' => $resource,
        ],
    );

    $pipe = new GenerateReportPipe;
    $result = $pipe($dto, fn (AnalysisResultDto $nextDto) => $nextDto);

    expect($result->report->modelEvidenceConflicts)->toHaveKey('posts');
    expect($result->report->modelEvidenceConflicts['posts'])->toHaveKey('payload');

    $conflict = $result->report->modelEvidenceConflicts['posts']['payload'];
    expect($conflict->expectedType)->toBe('array');
    expect($conflict->actualType)->toBe('string');
});

test('generate report ignores specific resource fields from ignored_fields config', function (): void {
    config()->set('migration-resource-checker.ignored_fields', [
        'remember_token' => ['resources'],
    ]);

    $resource = new ResourceReportDto(
        migrationFields: new FieldTable([
            'name' => new FieldDto('name', 'string', false),
            'remember_token' => new FieldDto('remember_token', 'string', true),
        ]),
        filamentFormFields: new FieldTable([
            'legacy_field' => new FieldDto('legacy_field', 'string', true),
            'remember_token' => new FieldDto('remember_token', 'string', true),
        ]),
    );

    $dto = new AnalysisResultDto(
        report: new ReportDto,
        resources: [
            'users' => $resource,
        ],
    );

    $pipe = new GenerateReportPipe;
    $result = $pipe($dto, fn (AnalysisResultDto $nextDto) => $nextDto);

    expect($result->report->addFieldsToFilamentForm)->toHaveKey('users');
    expect($result->report->addFieldsToFilamentForm['users'])->toHaveKey('name');
    expect($result->report->addFieldsToFilamentForm['users'])->not->toHaveKey('remember_token');

    expect($result->report->removeFieldsFromFilamentForm)->toHaveKey('users');
    expect($result->report->removeFieldsFromFilamentForm['users'])->toHaveKey('legacy_field');
    expect($result->report->removeFieldsFromFilamentForm['users'])->not->toHaveKey('remember_token');
});

test('generate report does not add model fields when they exist in phpdoc', function (): void {
    $resource = new ResourceReportDto(
        migrationFields: new FieldTable([
            'id' => new FieldDto('id', 'string', false),
            'created_at' => new FieldDto('created_at', 'Carbon', true),
            'updated_at' => new FieldDto('updated_at', 'Carbon', true),
        ]),
        phpdocFields: new PhpDocFieldTable([
            'id' => new PhpDocFieldDto('id', 'string', false),
            'created_at' => new PhpDocFieldDto('created_at', 'Illuminate\\Support\\Carbon', true),
            'updated_at' => new PhpDocFieldDto('updated_at', 'Illuminate\\Support\\Carbon', true),
        ]),
    );

    $dto = new AnalysisResultDto(
        report: new ReportDto,
        resources: [
            'users' => $resource,
        ],
    );

    $pipe = new GenerateReportPipe;
    $result = $pipe($dto, fn (AnalysisResultDto $nextDto) => $nextDto);

    expect($result->report->addFieldsToModels)->toBe([]);
});

test('generate report does not flag relationship phpdoc when class names match semantically', function (): void {
    $resource = new ResourceReportDto(
        phpdocReadFields: new PhpDocFieldTable([
            'devices' => new PhpDocFieldDto(
                name: 'devices',
                type: '\\Device',
                nullable: false,
                arrayType: 'Collection',
                keyType: 'array-key',
            ),
        ]),
        modelRelationships: new RelationshipFieldTable([
            'devices' => new RelationshipFieldDto(
                name: 'devices',
                type: 'HasMany',
                model: 'App\\Models\\Device',
            ),
        ]),
    );

    $dto = new AnalysisResultDto(
        report: new ReportDto,
        resources: [
            'activities' => $resource,
        ],
    );

    $pipe = new GenerateReportPipe;
    $result = $pipe($dto, fn (AnalysisResultDto $nextDto) => $nextDto);

    expect($result->report->shouldBeCamelCasePhpdocProperty)->toBe([]);
});

test('generate report does not flag raw collection generic relationship phpdoc type', function (): void {
    $resource = new ResourceReportDto(
        phpdocReadFields: new PhpDocFieldTable([
            'devices' => new PhpDocFieldDto(
                name: 'devices',
                type: 'Collection<array-key,\\Device>',
                nullable: false,
            ),
        ]),
        modelRelationships: new RelationshipFieldTable([
            'devices' => new RelationshipFieldDto(
                name: 'devices',
                type: 'HasMany',
                model: 'App\\Models\\Device',
            ),
        ]),
    );

    $dto = new AnalysisResultDto(
        report: new ReportDto,
        resources: [
            'activities' => $resource,
        ],
    );

    $pipe = new GenerateReportPipe;
    $result = $pipe($dto, fn (AnalysisResultDto $nextDto) => $nextDto);

    expect($result->report->shouldBeCamelCasePhpdocProperty)->toBe([]);
});

test('generate report does not flag decimal and encrypted casts as model evidence conflicts when phpdoc matches runtime type', function (): void {
    $resource = new ResourceReportDto(
        modelFields: new ModelFieldTable([
            'price' => new ModelFieldDto('price', cast: 'decimal:2'),
            'access_token' => new ModelFieldDto('access_token', cast: 'encrypted'),
        ]),
        phpdocFields: new PhpDocFieldTable([
            'price' => new PhpDocFieldDto(
                name: 'price',
                type: 'float',
                nullable: true,
            ),
            'access_token' => new PhpDocFieldDto(
                name: 'access_token',
                type: 'string',
                nullable: false,
            ),
        ]),
    );

    $dto = new AnalysisResultDto(
        report: new ReportDto,
        resources: [
            'kroger_examples' => $resource,
        ],
    );

    $pipe = new GenerateReportPipe;
    $result = $pipe($dto, fn (AnalysisResultDto $nextDto) => $nextDto);

    expect($result->report->modelEvidenceConflicts)->toBe([]);
});

test('generate report does not flag morphTo relationship with generic model phpdoc type', function (): void {
    $resource = new ResourceReportDto(
        phpdocReadFields: new PhpDocFieldTable([
            'trackable' => new PhpDocFieldDto(
                name: 'trackable',
                type: 'Illuminate\\Database\\Eloquent\\Model',
                nullable: true,
            ),
        ]),
        modelRelationships: new RelationshipFieldTable([
            'trackable' => new RelationshipFieldDto(
                name: 'trackable',
                type: 'MorphTo',
                model: 'App\\Models\\GpsArchive',
            ),
        ]),
    );

    $dto = new AnalysisResultDto(
        report: new ReportDto,
        resources: [
            'gps_archives' => $resource,
        ],
    );

    $pipe = new GenerateReportPipe;
    $result = $pipe($dto, fn (AnalysisResultDto $nextDto) => $nextDto);

    expect($result->report->shouldBeCamelCasePhpdocProperty)->toBe([]);
});

test('generate report exposes morphTo relationships in dedicated section', function (): void {
    $resource = new ResourceReportDto(
        phpdocReadFields: new PhpDocFieldTable([
            'trackable' => new PhpDocFieldDto(
                name: 'trackable',
                type: 'Illuminate\\Database\\Eloquent\\Model',
                nullable: true,
            ),
        ]),
        modelRelationships: new RelationshipFieldTable([
            'trackable' => new RelationshipFieldDto(
                name: 'trackable',
                type: 'MorphTo',
                model: 'App\\Models\\GpsArchive',
            ),
            'device' => new RelationshipFieldDto(
                name: 'device',
                type: 'BelongsTo',
                model: 'App\\Models\\Device',
            ),
        ]),
    );

    $dto = new AnalysisResultDto(
        report: new ReportDto,
        resources: [
            'gps_archives' => $resource,
        ],
    );

    $pipe = new GenerateReportPipe;
    $result = $pipe($dto, fn (AnalysisResultDto $nextDto) => $nextDto);

    expect($result->report->morphToRelationships)->toHaveKey('gps_archives');
    expect($result->report->morphToRelationships['gps_archives'])->toHaveKey('trackable');
    expect($result->report->morphToRelationships['gps_archives']['trackable'])->toMatchArray([
        'relationship_type' => 'MorphTo',
        'has_phpdoc_read' => true,
        'phpdoc_type' => 'Illuminate\\Database\\Eloquent\\Model',
        'nullable' => true,
    ]);
    expect($result->report->morphToRelationships['gps_archives'])->not->toHaveKey('device');
});

test('generate report does not flag documented accessor-backed fields as removable model fields', function (): void {
    $resource = new ResourceReportDto(
        modelFields: new ModelFieldTable([
            'status' => new ModelFieldDto(
                name: 'status',
                cast: 'App\\Enums\\ActivityStatus',
                accessor: true,
            ),
        ]),
        phpdocReadFields: new PhpDocFieldTable([
            'status' => new PhpDocFieldDto(
                name: 'status',
                type: 'App\\Enums\\ActivityStatus',
                nullable: false,
            ),
        ]),
    );

    $dto = new AnalysisResultDto(
        report: new ReportDto,
        resources: [
            'activities' => $resource,
        ],
    );

    $pipe = new GenerateReportPipe;
    $result = $pipe($dto, fn (AnalysisResultDto $nextDto) => $nextDto);

    expect($result->report->removeFieldsFromModels)->toBe([]);
    expect($result->report->addPropertyRead)->toBe([]);
});

test('generate report requests property-read for undocumented accessor-backed fields', function (): void {
    $resource = new ResourceReportDto(
        modelFields: new ModelFieldTable([
            'geohashes_for_display' => new ModelFieldDto(
                name: 'geohashes_for_display',
                cast: 'string',
                accessor: true,
                nullable: true,
            ),
            'batch' => new ModelFieldDto(
                name: 'batch',
                cast: 'Protos\\Gps\\GpsPointBatch',
                accessor: true,
                nullable: true,
            ),
        ]),
    );

    $dto = new AnalysisResultDto(
        report: new ReportDto,
        resources: [
            'gps_archives' => $resource,
        ],
    );

    $pipe = new GenerateReportPipe;
    $result = $pipe($dto, fn (AnalysisResultDto $nextDto) => $nextDto);

    expect($result->report->removeFieldsFromModels)->toBe([]);
    expect($result->report->addPropertyRead)->toHaveKey('gps_archives');
    expect($result->report->addPropertyRead['gps_archives'])->toHaveKey('geohashes_for_display');
    expect($result->report->addPropertyRead['gps_archives'])->toHaveKey('batch');
    expect($result->report->addPropertyRead['gps_archives']['geohashes_for_display']->nullable)->toBeTrue();
    expect($result->report->addPropertyRead['gps_archives']['batch']->type)->toBe('Protos\\Gps\\GpsPointBatch');
});