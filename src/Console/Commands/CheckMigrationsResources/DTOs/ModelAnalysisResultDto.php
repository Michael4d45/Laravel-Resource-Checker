<?php

declare(strict_types=1);

namespace Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs;

class ModelAnalysisResultDto
{
    public function __construct(
        public string $tableName,
        public ModelFieldTable $modelFields = new ModelFieldTable,
        public RelationshipFieldTable $modelRelationships = new RelationshipFieldTable,
    ) {}
}
