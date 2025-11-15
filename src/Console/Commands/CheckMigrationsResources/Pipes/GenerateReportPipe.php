<?php

declare(strict_types=1);

namespace Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\Pipes;

use Illuminate\Support\Str;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\AnalysisResultDto;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\FieldDto;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\FieldTable;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\ReportDto;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\WrongRelationshipNameDto;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\WrongTypeDto;

class GenerateReportPipe
{
    /** @var array<string> */
    private array $ignoreForResources = [];

    /** @var array<string> */
    private array $ignoreForModels = [];

    /** @var array<string> */
    private array $ignoreForPhpDoc = [];

    /** @var array<string> */
    private array $ignoreFieldsForModels = [];

    public function __invoke(AnalysisResultDto $dto, \Closure $next): AnalysisResultDto
    {
        // Base tables ignored across all components
        $ignoredTablesConfig = config()->array('migration-resource-checker.ignored_tables', []);

        // Extract ignored tables for each component
        /** @var array<string, array<string>> $ignoredTablesConfig */
        $this->ignoreForResources = $this->getIgnoredTablesForComponent($ignoredTablesConfig, 'resources');
        $this->ignoreForModels = $this->getIgnoredTablesForComponent($ignoredTablesConfig, 'models');
        $this->ignoreForPhpDoc = $this->getIgnoredTablesForComponent($ignoredTablesConfig, 'phpdoc');

        // Extract ignored fields for each component
        $ignoredFieldsConfig = config()->array('migration-resource-checker.ignored_fields', []);
        /** @var array<string, array<string>> $ignoredFieldsConfig */
        $this->ignoreFieldsForModels = $this->getIgnoredFieldsForComponent($ignoredFieldsConfig, 'models');

        $dto->report = new ReportDto(
            addFieldsToFilamentForm: $this->addFieldsToFilamentForm($dto),
            removeFieldsFromFilamentForm: $this->removeFieldsFromFilamentForm($dto),
            addFilamentResources: $this->addFilamentResources($dto),
            removeFilamentResources: $this->removeFilamentResources($dto),
            addFieldsToModels: $this->addFieldsToModels($dto),
            removeFieldsFromModels: $this->removeFieldsFromModels($dto),
            addModels: $this->addModels($dto),
            removeModels: $this->removeModels($dto),
            addFieldsToModelDocs: $this->addFieldsToModelDocs($dto),
            removeFieldsFromModelDocs: $this->removeFieldsFromModelDocs($dto),
            wrongModelDocTypes: $this->wrongModelDocTypes($dto),
            shouldBeCamelCasePhpdocProperty: $this->shouldBeCamelCasePhpdocProperty($dto),
            shouldBeCamelCaseRelationship: $this->shouldBeCamelCaseRelationship($dto),
            addPropertyRead: $this->addPropertyRead($dto),
        );

        return $next($dto);
    }

    /**
     * Get the list of tables that should be ignored for a specific component.
     *
     * @param  array<string, array<string>>  $ignoredTablesConfig
     * @return array<string>
     */
    private function getIgnoredTablesForComponent(array $ignoredTablesConfig, string $component): array
    {
        $ignoredTables = [];
        foreach ($ignoredTablesConfig as $table => $components) {
            if (in_array($component, $components, true)) {
                $ignoredTables[] = $table;
            }
        }

        return $ignoredTables;
    }

    /**
     * Get the list of fields that should be ignored for a specific component.
     *
     * @param  array<string, array<string>>  $ignoredFieldsConfig
     * @return array<string>
     */
    private function getIgnoredFieldsForComponent(array $ignoredFieldsConfig, string $component): array
    {
        $ignoredFields = [];
        foreach ($ignoredFieldsConfig as $field => $components) {
            if (in_array($component, $components, true)) {
                $ignoredFields[] = $field;
            }
        }

        return $ignoredFields;
    }

    /**
     * @return array<string, FieldTable>
     */
    private function addFieldsToFilamentForm(AnalysisResultDto $dto): array
    {
        $result = [];
        foreach ($dto->resources as $table => $resourceReport) {
            if (in_array($table, $this->ignoreForResources)) {
                continue;
            }
            $toAdd = new FieldTable;
            foreach ($resourceReport->migrationFields as $fieldName => $fieldDto) {
                if (! $resourceReport->filamentFormFields->has($fieldName)) {
                    $toAdd->put($fieldName, $fieldDto);
                }
            }
            if ($toAdd->isNotEmpty()) {
                $result[$table] = $toAdd;
            }
        }

        return $result;
    }

    /**
     * @return array<string, FieldTable>
     */
    private function removeFieldsFromFilamentForm(AnalysisResultDto $dto): array
    {
        $result = [];
        foreach ($dto->resources as $table => $resourceReport) {
            if (in_array($table, $this->ignoreForResources)) {
                continue;
            }
            $toRemove = new FieldTable;
            foreach ($resourceReport->filamentFormFields as $fieldName => $fieldDto) {
                if (! $resourceReport->migrationFields->has($fieldName)) {
                    $toRemove->put($fieldName, $fieldDto);
                }
            }
            if ($toRemove->isNotEmpty()) {
                $result[$table] = $toRemove;
            }
        }

        return $result;
    }

    /**
     * @return array<string>
     */
    private function addFilamentResources(AnalysisResultDto $dto): array
    {
        $result = [];
        foreach ($dto->resources as $table => $resourceReport) {
            if (in_array($table, $this->ignoreForResources)) {
                continue;
            }
            if ($resourceReport->filamentFormFields->isEmpty()) {
                $result[] = $table;
            }
        }

        return $result;
    }

    /**
     * @return array<string>
     */
    private function removeFilamentResources(AnalysisResultDto $dto): array
    {
        return [];
    }

    /**
     * @return array<string, FieldTable>
     */
    private function addFieldsToModels(AnalysisResultDto $dto): array
    {
        $result = [];
        foreach ($dto->resources as $table => $resourceReport) {
            if (in_array($table, $this->ignoreForModels)) {
                continue;
            }
            $toAdd = new FieldTable;
            foreach ($resourceReport->migrationFields as $fieldName => $fieldDto) {
                if (! $resourceReport->modelFields->has($fieldName) && ! in_array($fieldName, $this->ignoreFieldsForModels)) {
                    $toAdd->put($fieldName, $fieldDto);
                }
            }
            if ($toAdd->isNotEmpty()) {
                $result[$table] = $toAdd;
            }
        }

        return $result;
    }

    /**
     * @return array<string, FieldTable>
     */
    private function removeFieldsFromModels(AnalysisResultDto $dto): array
    {
        $result = [];
        foreach ($dto->resources as $table => $resourceReport) {
            if (in_array($table, $this->ignoreForModels)) {
                continue;
            }
            $toRemove = new FieldTable;
            foreach ($resourceReport->modelFields as $fieldName => $fieldDto) {
                if (! $resourceReport->migrationFields->has($fieldName)) {
                    $toRemove->put($fieldName, new FieldDto($fieldDto->name, $fieldDto->cast ?? 'mixed', false));
                }
            }
            if ($toRemove->isNotEmpty()) {
                $result[$table] = $toRemove;
            }
        }

        return $result;
    }

    /**
     * @return array<string>
     */
    private function addModels(AnalysisResultDto $dto): array
    {
        $result = [];
        foreach ($dto->resources as $table => $resourceReport) {
            if (in_array($table, $this->ignoreForModels)) {
                continue;
            }
            if ($resourceReport->modelFields->isEmpty()) {
                $result[] = $table;
            }
        }

        return $result;
    }

    /**
     * @return array<string>
     */
    private function removeModels(AnalysisResultDto $dto): array
    {
        return [];
    }

    /**
     * @return array<string, FieldTable>
     */
    private function addFieldsToModelDocs(AnalysisResultDto $dto): array
    {
        $result = [];
        foreach ($dto->resources as $table => $resourceReport) {
            if (in_array($table, $this->ignoreForPhpDoc)) {
                continue;
            }
            $toAdd = new FieldTable;
            foreach ($resourceReport->migrationFields as $fieldName => $fieldDto) {
                if (! $resourceReport->phpdocFields->has($fieldName)) {
                    $toAdd->put($fieldName, $fieldDto);
                }
            }
            if ($toAdd->isNotEmpty()) {
                $result[$table] = $toAdd;
            }
        }

        return $result;
    }

    /**
     * @return array<string, FieldTable>
     */
    private function removeFieldsFromModelDocs(AnalysisResultDto $dto): array
    {
        $result = [];
        foreach ($dto->resources as $table => $resourceReport) {
            if (in_array($table, $this->ignoreForPhpDoc)) {
                continue;
            }
            $toRemove = new FieldTable;
            foreach ($resourceReport->phpdocFields as $fieldName => $phpDocDto) {
                if (! $resourceReport->migrationFields->has($fieldName)) {
                    // Create a FieldDto from PhpDocDto? But return FieldTable, so need FieldDto.
                    // Perhaps put the migration one if exists, but since not, maybe skip or create dummy.
                    // For remove, perhaps use the phpdoc as FieldDto, but FieldDto has name,type,nullable.
                    // PhpDocDto has type, nullable.
                    $toRemove->put($fieldName, new FieldDto($fieldName, $phpDocDto->type, $phpDocDto->nullable));
                }
            }
            if ($toRemove->isNotEmpty()) {
                $result[$table] = $toRemove;
            }
        }

        return $result;
    }

    /**
     * @return array<string, array<string, WrongTypeDto>>
     */
    private function wrongModelDocTypes(AnalysisResultDto $dto): array
    {
        $result = [];
        foreach ($dto->resources as $table => $resourceReport) {
            if (in_array($table, $this->ignoreForPhpDoc)) {
                continue;
            }
            $wrong = [];
            foreach ($resourceReport->phpdocFields as $fieldName => $phpDocDto) {
                if ($resourceReport->migrationFields->has($fieldName)) {
                    $migrationDto = $resourceReport->migrationFields->get($fieldName);
                    if ($migrationDto === null) {
                        continue;
                    }
                    $cast = $resourceReport->modelFields->get($fieldName)?->cast;
                    $expectedType = $cast ? $this->getExpectedPhpDocTypeFromCast($cast) : $this->getExpectedPhpDocType($migrationDto->type);
                    $expectedNullable = $migrationDto->nullable;

                    $effectiveActualType = $phpDocDto->type;
                    if ($phpDocDto->type === 'mixed' && $cast === 'array') {
                        $effectiveActualType = 'array';
                    }

                    if ($effectiveActualType !== $expectedType || $phpDocDto->nullable !== $expectedNullable) {
                        $wrong[$fieldName] = new WrongTypeDto($fieldName, $expectedType, $effectiveActualType, $expectedNullable, $phpDocDto->nullable);
                    }
                }
            }
            if (! empty($wrong)) {
                $result[$table] = $wrong;
            }
        }

        return $result;
    }

    private function getExpectedPhpDocType(string $migrationType): string
    {
        $normalizations = config()->array('migration-resource-checker.type_normalizations', []);
        $expectedType = $normalizations[$migrationType] ?? $migrationType;
        assert(is_string($expectedType));

        return $expectedType;
    }

    private function getExpectedPhpDocTypeFromCast(string $cast): string
    {
        $castMappings = config()->array('migration-resource-checker.cast_type_mappings', []);
        $expectedType = $castMappings[$cast] ?? $cast;
        assert(is_string($expectedType));
        return $expectedType;
    }

    /**
     * @return array<string, FieldTable>
     */
    private function shouldBeCamelCasePhpdocProperty(AnalysisResultDto $dto): array
    {
        $result = [];
        foreach ($dto->resources as $table => $resourceReport) {
            if (in_array($table, $this->ignoreForPhpDoc)) {
                continue;
            }
            $wrong = new FieldTable;
            foreach ($resourceReport->phpdocReadFields as $fieldName => $phpDocDto) {
                if ($resourceReport->modelRelationships->has($fieldName)) {
                    $relDto = $resourceReport->modelRelationships->get($fieldName);
                    if ($relDto === null) {
                        continue;
                    }
                    if ($phpDocDto->type !== $relDto->model) {
                        $wrong->put($fieldName, new FieldDto($fieldName, $phpDocDto->type, $phpDocDto->nullable));
                    }
                }
            }
            if ($wrong->isNotEmpty()) {
                $result[$table] = $wrong;
            }
        }

        return $result;
    }

    /**
     * @return array<string, array<string, WrongRelationshipNameDto>>
     */
    private function shouldBeCamelCaseRelationship(AnalysisResultDto $dto): array
    {
        $result = [];
        foreach ($dto->resources as $table => $resourceReport) {
            if (in_array($table, $this->ignoreForPhpDoc)) {
                continue;
            }
            $wrong = [];
            foreach ($resourceReport->modelRelationships as $relName => $relDto) {
                if (str_contains($relName, '_')) {
                    $expectedName = Str::camel($relName);
                    if ($relName !== $expectedName) {
                        $wrong[$relName] = new WrongRelationshipNameDto($relName, $expectedName);
                    }
                }
            }
            if (! empty($wrong)) {
                $result[$table] = $wrong;
            }
        }

        return $result;
    }

    /**
     * @return array<string, FieldTable>
     */
    private function addPropertyRead(AnalysisResultDto $dto): array
    {
        $result = [];
        foreach ($dto->resources as $table => $resourceReport) {
            if (in_array($table, $this->ignoreForPhpDoc)) {
                continue;
            }
            $toAdd = new FieldTable;
            foreach ($resourceReport->modelRelationships as $relName => $relDto) {
                if (! $resourceReport->phpdocReadFields->has($relName)) {
                    $toAdd->put($relName, new FieldDto($relName, $relDto->model, false));
                }
            }
            if ($toAdd->isNotEmpty()) {
                $result[$table] = $toAdd;
            }
        }

        return $result;
    }
}
