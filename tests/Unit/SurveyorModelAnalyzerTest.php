<?php

declare(strict_types=1);

use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\Services\SurveyorModelAnalyzer;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\AstHelper;

require_once __DIR__.'/../Fixtures/Models/SurveyorPost.php';

test('surveyor model analyzer can be resolved', function (): void {
    expect(app(SurveyorModelAnalyzer::class))->toBeInstanceOf(SurveyorModelAnalyzer::class);
});

test('surveyor analyzer resolves model fields and relations', function (): void {
    $analyzer = app(SurveyorModelAnalyzer::class);
    $ast = (new AstHelper)->parseFile(__DIR__.'/../Fixtures/Models/SurveyorPost.php');
    expect($ast)->not->toBeNull();

    $analysis = $analyzer->analyze(
        Tests\Fixtures\Models\SurveyorPost::class,
        $ast ?? [],
        'Tests\\Fixtures\\Models',
    );

    expect($analysis->tableName)->toBe('surveyor_posts');
    expect($analysis->modelFields->get('title'))->not->toBeNull();
    expect($analysis->modelFields->get('title')?->fillable)->toBeTrue();
    expect($analysis->modelFields->get('published_at')?->cast)->toBe('datetime');
    expect($analysis->modelFields->get('status')?->accessor)->toBeTrue();
    expect($analysis->modelFields->get('status')?->cast)->toBe('string');
    expect($analysis->modelFields->get('created_at')?->cast)->toBeNull();
    expect($analysis->modelRelationships->get('user')?->type)->toBe('BelongsTo');
    expect($analysis->modelRelationships->get('user')?->model)->toContain('SurveyorUser');
});