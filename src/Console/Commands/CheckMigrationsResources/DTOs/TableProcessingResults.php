<?php

declare(strict_types=1);

namespace Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs;

class TableProcessingResults
{
    public function __construct(
        /** @var array<string, FieldTable> */
        public array $addFieldsToResources = [],
        /** @var array<string, FieldTable> */
        public array $removeFieldsFromResources = [],
        /** @var array<string, FieldTable> */
        public array $addFieldsToModels = [],
        /** @var array<string, FieldTable> */
        public array $removeFieldsFromModels = [],
        /** @var array<string, FieldTable> */
        public array $addFieldsToModelDocs = [],
        /** @var array<string, FieldTable> */
        public array $removeFieldsFromModelDocs = [],
        /** @var array<string, FieldTable> */
        public array $wrongModelDocTypes = [],
        /** @var array<string, FieldTable> */
        public array $addPropertyRead = [],
    ) {}
}
