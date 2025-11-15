<?php

declare(strict_types=1);

namespace Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs;

class TableProcessingData
{
    public function __construct(
        public string $table,
        public FieldTable $migCols,
        public FieldTable $resCols,
        public ModelFieldTable $modCols,
        public FieldTable $phpdocCols,
        public FieldTable $modelRelationships,
        public FieldTable $phpdocRead,
        /** @var array<string> */
        public array $ignoreForResources,
        /** @var array<string> */
        public array $ignoreForModels,
        /** @var array<string> */
        public array $ignoreForPhpDoc,
    ) {}
}
