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

    /** @var array<string> */
    private array $ignoreFieldsForResources = [];

    public function __invoke(
        AnalysisResultDto $dto,
        \Closure $next,
    ): AnalysisResultDto {
        // Base tables ignored across all components
        $ignoredTablesConfig = config()->array('migration-resource-checker.ignored_tables', []);

        // Extract ignored tables for each component
        /** @var array<string, array<string>> $ignoredTablesConfig */
        $this->ignoreForResources = $this->getIgnoredTablesForComponent(
            $ignoredTablesConfig,
            'resources',
        );
        $this->ignoreForModels = $this->getIgnoredTablesForComponent(
            $ignoredTablesConfig,
            'models',
        );
        $this->ignoreForPhpDoc = $this->getIgnoredTablesForComponent(
            $ignoredTablesConfig,
            'phpdoc',
        );

        // Extract ignored fields for each component
        $ignoredFieldsConfig = config()->array('migration-resource-checker.ignored_fields', []);
        /** @var array<string, array<string>> $ignoredFieldsConfig */
        $this->ignoreFieldsForResources = $this->getIgnoredFieldsForComponent(
            $ignoredFieldsConfig,
            'resources',
        );
        $this->ignoreFieldsForModels = $this->getIgnoredFieldsForComponent(
            $ignoredFieldsConfig,
            'models',
        );

        $dto->report = new ReportDto(
            addFieldsToFilamentForm: $this->addFieldsToFilamentForm($dto),
            removeFieldsFromFilamentForm: $this->removeFieldsFromFilamentForm(
                $dto,
            ),
            addFilamentResources: $this->addFilamentResources($dto),
            removeFilamentResources: $this->removeFilamentResources($dto),
            addFieldsToModels: $this->addFieldsToModels($dto),
            removeFieldsFromModels: $this->removeFieldsFromModels($dto),
            addModels: $this->addModels($dto),
            removeModels: $this->removeModels($dto),
            addFieldsToModelDocs: $this->addFieldsToModelDocs($dto),
            removeFieldsFromModelDocs: $this->removeFieldsFromModelDocs($dto),
            wrongModelDocTypes: $this->wrongModelDocTypes($dto),
            shouldBeCamelCasePhpdocProperty: $this->shouldBeCamelCasePhpdocProperty(
                $dto,
            ),
            shouldBeCamelCaseRelationship: $this->shouldBeCamelCaseRelationship(
                $dto,
            ),
            addPropertyRead: $this->addPropertyRead($dto),
            morphToRelationships: $this->morphToRelationships($dto),
            modelEvidenceConflicts: $this->modelEvidenceConflicts($dto),
        );

        return $next($dto);
    }

    /**
     * Get the list of tables that should be ignored for a specific component.
     *
     * @param  array<string, array<string>>  $ignoredTablesConfig
     * @return array<string>
     */
    private function getIgnoredTablesForComponent(
        array $ignoredTablesConfig,
        string $component,
    ): array {
        $ignoredTables = [];
        foreach ($ignoredTablesConfig as $table => $components) {
            if (!in_array($component, $components, true)) {
                continue;
            }

            $ignoredTables[] = $table;
        }

        return $ignoredTables;
    }

    /**
     * Get the list of fields that should be ignored for a specific component.
     *
     * @param  array<string, array<string>>  $ignoredFieldsConfig
     * @return array<string>
     */
    private function getIgnoredFieldsForComponent(
        array $ignoredFieldsConfig,
        string $component,
    ): array {
        $ignoredFields = [];
        foreach ($ignoredFieldsConfig as $field => $components) {
            if (!in_array($component, $components, true)) {
                continue;
            }

            $ignoredFields[] = $field;
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
            if (in_array($table, $this->ignoreForResources, true)) {
                continue;
            }
            $toAdd = new FieldTable;
            foreach ($resourceReport->migrationFields as $fieldName => $fieldDto) {
                if (in_array(
                    $fieldName,
                    $this->ignoreFieldsForResources,
                    true,
                )) {
                    continue;
                }

                if ($resourceReport->filamentFormFields->has($fieldName)) {
                    continue;
                }

                $toAdd->put($fieldName, $fieldDto);
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
            if (in_array($table, $this->ignoreForResources, true)) {
                continue;
            }
            $toRemove = new FieldTable;
            foreach ($resourceReport->filamentFormFields as $fieldName => $fieldDto) {
                if (in_array(
                    $fieldName,
                    $this->ignoreFieldsForResources,
                    true,
                )) {
                    continue;
                }

                if ($resourceReport->migrationFields->has($fieldName)) {
                    continue;
                }

                $toRemove->put($fieldName, $fieldDto);
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
            if (in_array($table, $this->ignoreForResources, true)) {
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
            if (in_array($table, $this->ignoreForModels, true)) {
                continue;
            }
            $toAdd = new FieldTable;
            foreach ($resourceReport->migrationFields as $fieldName => $fieldDto) {
                $hasModelEvidence =
                    $resourceReport->modelFields->has($fieldName)
                    || $resourceReport->phpdocFields->has($fieldName);

                if (
                    !(
                        !$hasModelEvidence
                        && !in_array(
                            $fieldName,
                            $this->ignoreFieldsForModels,
                            true,
                        )
                    )
                ) {
                    continue;
                }

                $toAdd->put($fieldName, $fieldDto);
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
            if (in_array($table, $this->ignoreForModels, true)) {
                continue;
            }
            $toRemove = new FieldTable;
            foreach ($resourceReport->modelFields as $fieldName => $fieldDto) {
                if ($fieldDto->accessor) {
                    continue;
                }

                if ($resourceReport->migrationFields->has($fieldName)) {
                    continue;
                }

                $toRemove->put(
                    $fieldName,
                    new FieldDto(
                        $fieldDto->name,
                        $fieldDto->cast ?? 'mixed',
                        false,
                    ),
                );
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
            if (in_array($table, $this->ignoreForModels, true)) {
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
            if (in_array($table, $this->ignoreForPhpDoc, true)) {
                continue;
            }
            $toAdd = new FieldTable;
            foreach ($resourceReport->migrationFields as $fieldName => $fieldDto) {
                if ($resourceReport->phpdocFields->has($fieldName)) {
                    continue;
                }

                $toAdd->put($fieldName, $fieldDto);
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
            if (in_array($table, $this->ignoreForPhpDoc, true)) {
                continue;
            }
            $toRemove = new FieldTable;
            foreach ($resourceReport->phpdocFields as $fieldName => $phpDocDto) {
                if ($resourceReport->migrationFields->has($fieldName)) {
                    continue;
                }

                $toRemove->put(
                    $fieldName,
                    new FieldDto(
                        $fieldName,
                        $phpDocDto->type,
                        $phpDocDto->nullable,
                    ),
                );
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
            if (in_array($table, $this->ignoreForPhpDoc, true)) {
                continue;
            }
            $wrong = [];
            foreach ($resourceReport->phpdocFields as $fieldName => $phpDocDto) {
                if (!$resourceReport->migrationFields->has($fieldName)) {
                    continue;
                }

                $migrationDto =
                    $resourceReport->migrationFields->get($fieldName);
                if ($migrationDto === null) {
                    continue;
                }
                $cast = $resourceReport->modelFields->get($fieldName)?->cast;
                $expectedType = $cast
                    ? $this->getExpectedPhpDocTypeFromCast($cast)
                    : $this->getExpectedPhpDocType($migrationDto->type);
                $expectedNullable = $migrationDto->nullable;

                $effectiveActualType = $phpDocDto->type;
                if ($phpDocDto->type === 'mixed' && $cast === 'array') {
                    $effectiveActualType = 'array';
                }

                // Handle array types in PHPDoc (e.g., array<string, string>, Collection<Type>)
                if (
                    $expectedType === 'array'
                    && (
                        $phpDocDto->arrayType === 'array'
                        || $phpDocDto->arrayType === 'Collection'
                    )
                ) {
                    $effectiveActualType = 'array';
                }

                if (
                    $effectiveActualType !== $expectedType
                    || $phpDocDto->nullable !== $expectedNullable
                ) {
                    $wrong[$fieldName] = new WrongTypeDto(
                        $fieldName,
                        $expectedType,
                        $effectiveActualType,
                        $expectedNullable,
                        $phpDocDto->nullable,
                    );
                }
            }
            if (!empty($wrong)) {
                $result[$table] = $wrong;
            }
        }

        return $result;
    }

    private function getExpectedPhpDocType(string $migrationType): string
    {
        return match ($migrationType) {
            'Carbon' => 'Illuminate\\Support\\Carbon',
            'Point' => 'Clickbar\\Magellan\\Data\\Geometries\\Point',
            'Box2D' => 'Clickbar\\Magellan\\Data\\Boxes\\Box2D',
            default => $migrationType,
        };
    }

    private function getExpectedPhpDocTypeFromCast(string $cast): string
    {
        $normalizedCast = strtolower(trim($cast));

        if ($normalizedCast === '') {
            return 'mixed';
        }

        [$baseCast, $castArgument] = array_pad(
            explode(':', $normalizedCast, 2),
            2,
            null,
        );

        if ($baseCast === 'encrypted') {
            return match ($castArgument) {
                'array', 'json', 'collection' => 'array',
                'object' => 'object',
                default => 'string',
            };
        }

        return match ($baseCast) {
            'datetime',
            'immutable_datetime',
            'timestamp',
            'immutable_timestamp',
                => 'Illuminate\\Support\\Carbon',
            'json', 'array', 'collection' => 'array',
            'boolean', 'bool' => 'bool',
            'integer', 'int' => 'int',
            'real', 'float', 'double', 'decimal' => 'float',
            'hashed' => 'string',
            default => $cast,
        };
    }

    /**
     * @return array<string, FieldTable>
     */
    private function shouldBeCamelCasePhpdocProperty(AnalysisResultDto $dto): array
    {
        $result = [];
        foreach ($dto->resources as $table => $resourceReport) {
            if (in_array($table, $this->ignoreForPhpDoc, true)) {
                continue;
            }
            $wrong = new FieldTable;
            foreach ($resourceReport->phpdocReadFields as $fieldName => $phpDocDto) {
                if (!$resourceReport->modelRelationships->has($fieldName)) {
                    continue;
                }

                $relDto = $resourceReport->modelRelationships->get($fieldName);
                if ($relDto === null) {
                    continue;
                }

                // morphTo is polymorphic by design, so a concrete model type cannot be
                // reliably validated against @property-read.
                if ($this->isPolymorphicMorphToRelationship($relDto->type)) {
                    continue;
                }

                $matchesModel = $this->relationshipPhpDocTypeMatchesModel(
                    $phpDocDto->type,
                    $relDto->model,
                );
                $isCollectionRelationship = $this->relationshipExpectsCollection($relDto->type);
                $rawTypeLooksLikeCollection = $this->rawPhpDocTypeIsCollection($phpDocDto->type);
                $hasCollectionPhpDocType =
                    in_array(
                        $phpDocDto->arrayType,
                        ['Collection', 'array'],
                        true,
                    ) || $rawTypeLooksLikeCollection;

                if (
                    !$matchesModel
                    || $isCollectionRelationship && !$hasCollectionPhpDocType
                ) {
                    $wrong->put(
                        $fieldName,
                        new FieldDto(
                            $fieldName,
                            $phpDocDto->type,
                            $phpDocDto->nullable,
                        ),
                    );
                }
            }
            if ($wrong->isNotEmpty()) {
                $result[$table] = $wrong;
            }
        }

        return $result;
    }

    private function relationshipPhpDocTypeMatchesModel(
        string $phpDocType,
        string $relationshipModel,
    ): bool {
        $normalizedPhpDocType =
            $this->extractRelationshipModelTypeFromPhpDoc($phpDocType);

        if ($normalizedPhpDocType === null) {
            return false;
        }

        $normalizedPhpDocType = ltrim(trim($normalizedPhpDocType), '\\');
        $normalizedRelationshipModel = ltrim(trim($relationshipModel), '\\');

        if ($normalizedPhpDocType === $normalizedRelationshipModel) {
            return true;
        }

        return (
            class_basename($normalizedPhpDocType) === class_basename(
                $normalizedRelationshipModel,
            )
        );
    }

    private function extractRelationshipModelTypeFromPhpDoc(string $phpDocType): string|null
    {
        $normalizedPhpDocType = ltrim(trim($phpDocType), '\\');

        if ($normalizedPhpDocType === '') {
            return null;
        }

        if (
            preg_match(
                '/^(Collection|array)\s*<\s*([^,>]+)\s*,\s*([^>]+)\s*>$/i',
                $normalizedPhpDocType,
                $matches,
            ) === 1
        ) {
            return trim($matches[3]);
        }

        if (
            preg_match(
                '/^(Collection|array)\s*<\s*([^>]+)\s*>$/i',
                $normalizedPhpDocType,
                $matches,
            ) === 1
        ) {
            return trim($matches[2]);
        }

        if (str_ends_with($normalizedPhpDocType, '[]')) {
            return substr($normalizedPhpDocType, 0, -2);
        }

        return $normalizedPhpDocType;
    }

    private function rawPhpDocTypeIsCollection(string $phpDocType): bool
    {
        return (
            preg_match('/^(Collection|array)\s*</i', trim($phpDocType)) === 1
            || str_ends_with(trim($phpDocType), '[]')
        );
    }

    private function relationshipExpectsCollection(string $relationshipType): bool
    {
        return in_array(
            strtolower($relationshipType),
            [
                'hasmany',
                'belongstomany',
                'morphmany',
                'morphtomany',
                'hasmanythrough',
                'collection',
            ],
            true,
        );
    }

    private function isPolymorphicMorphToRelationship(string $relationshipType): bool
    {
        return strtolower(trim($relationshipType)) === 'morphto';
    }

    /**
     * @return array<string, array<string, WrongRelationshipNameDto>>
     */
    private function shouldBeCamelCaseRelationship(AnalysisResultDto $dto): array
    {
        $result = [];
        foreach ($dto->resources as $table => $resourceReport) {
            if (in_array($table, $this->ignoreForPhpDoc, true)) {
                continue;
            }
            $wrong = [];
            foreach ($resourceReport->modelRelationships as $relName => $relDto) {
                if (!str_contains($relName, '_')) {
                    continue;
                }

                $expectedName = Str::camel($relName);
                if ($relName !== $expectedName) {
                    $wrong[$relName] = new WrongRelationshipNameDto(
                        $relName,
                        $expectedName,
                    );
                }
            }
            if (!empty($wrong)) {
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
            if (in_array($table, $this->ignoreForPhpDoc, true)) {
                continue;
            }
            $toAdd = new FieldTable;
            foreach ($resourceReport->modelRelationships as $relName => $relDto) {
                if ($resourceReport->phpdocReadFields->has($relName)) {
                    continue;
                }

                $toAdd->put(
                    $relName,
                    new FieldDto($relName, $relDto->model, false),
                );
            }

            foreach ($resourceReport->modelFields as $fieldName => $fieldDto) {
                if (!$fieldDto->accessor) {
                    continue;
                }

                if ($resourceReport->phpdocReadFields->has($fieldName)) {
                    continue;
                }

                $toAdd->put(
                    $fieldName,
                    new FieldDto(
                        $fieldName,
                        $fieldDto->cast ?? 'mixed',
                        $fieldDto->nullable,
                    ),
                );
            }

            if ($toAdd->isNotEmpty()) {
                $result[$table] = $toAdd;
            }
        }

        return $result;
    }

    /**
     * Reports discovered morphTo relationships and their @property-read coverage.
     *
     * @return array<string, array<string, array{relationship_type: string, has_phpdoc_read: bool, phpdoc_type: string|null, nullable: bool|null}>>
     */
    private function morphToRelationships(AnalysisResultDto $dto): array
    {
        $result = [];

        foreach ($dto->resources as $table => $resourceReport) {
            if (in_array($table, $this->ignoreForPhpDoc, true)) {
                continue;
            }

            $morphTo = [];
            foreach ($resourceReport->modelRelationships as $relName => $relDto) {
                if (!$this->isPolymorphicMorphToRelationship($relDto->type)) {
                    continue;
                }

                $phpDoc = $resourceReport->phpdocReadFields->get($relName);
                $morphTo[$relName] = [
                    'relationship_type' => $relDto->type,
                    'has_phpdoc_read' => $phpDoc !== null,
                    'phpdoc_type' => $phpDoc?->type,
                    'nullable' => $phpDoc?->nullable,
                ];
            }

            if (!empty($morphTo)) {
                $result[$table] = $morphTo;
            }
        }

        return $result;
    }

    /**
     * Reports type contradictions between model evidence (casts) and docblock tags,
     * even when migration evidence is missing.
     *
     * @return array<string, array<string, WrongTypeDto>>
     */
    private function modelEvidenceConflicts(AnalysisResultDto $dto): array
    {
        $result = [];

        foreach ($dto->resources as $table => $resourceReport) {
            if (in_array($table, $this->ignoreForPhpDoc, true)) {
                continue;
            }

            $conflicts = [];

            foreach ($resourceReport->phpdocFields as $fieldName => $phpDocDto) {
                $modelField = $resourceReport->modelFields->get($fieldName);
                $cast = $modelField?->cast;

                if ($cast === null || $cast === '') {
                    continue;
                }

                $expectedType = $this->getExpectedPhpDocTypeFromCast($cast);
                $actualType = $phpDocDto->type;

                if (
                    $expectedType === 'array'
                    && (
                        $phpDocDto->arrayType === 'array'
                        || $phpDocDto->arrayType === 'Collection'
                    )
                ) {
                    $actualType = 'array';
                }

                if ($expectedType === $actualType) {
                    continue;
                }

                $conflicts[$fieldName] = new WrongTypeDto(
                    fieldName: $fieldName,
                    expectedType: $expectedType,
                    actualType: $actualType,
                    expectedNullable: $phpDocDto->nullable,
                    actualNullable: $phpDocDto->nullable,
                );
            }

            if (!empty($conflicts)) {
                $result[$table] = $conflicts;
            }
        }

        return $result;
    }
}
