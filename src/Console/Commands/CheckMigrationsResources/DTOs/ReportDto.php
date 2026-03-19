<?php

declare(strict_types=1);

namespace Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;

/**
 * @implements Arrayable<string, mixed>
 */
class ReportDto implements Arrayable
{
    /**
     * @param  array<string, FieldTable>  $addFieldsToFilamentForm
     * @param  array<string, FieldTable>  $removeFieldsFromFilamentForm
     * @param  array<string>  $addFilamentResources
     * @param  array<string>  $removeFilamentResources
     * @param  array<string, FieldTable>  $addFieldsToModels
     * @param  array<string, FieldTable>  $removeFieldsFromModels
     * @param  array<string>  $addModels
     * @param  array<string>  $removeModels
     * @param  array<string, FieldTable>  $addFieldsToModelDocs
     * @param  array<string, FieldTable>  $removeFieldsFromModelDocs
     * @param  array<string, array<string, WrongTypeDto>>  $wrongModelDocTypes
     * @param  array<string, FieldTable>  $shouldBeCamelCasePhpdocProperty
     * @param  array<string, array<string, WrongRelationshipNameDto>>  $shouldBeCamelCaseRelationship
     * @param  array<string, FieldTable>  $addPropertyRead
     * @param  array<string, array<string, array{relationship_type: string, has_phpdoc_read: bool, phpdoc_type: string|null, nullable: bool|null}>>  $morphToRelationships
     * @param  array<string, array<string, WrongTypeDto>>  $modelEvidenceConflicts
     */
    public function __construct(
        public array $addFieldsToFilamentForm = [],
        public array $removeFieldsFromFilamentForm = [],
        public array $addFilamentResources = [],
        public array $removeFilamentResources = [],
        public array $addFieldsToModels = [],
        public array $removeFieldsFromModels = [],
        public array $addModels = [],
        public array $removeModels = [],
        public array $addFieldsToModelDocs = [],
        public array $removeFieldsFromModelDocs = [],
        public array $wrongModelDocTypes = [],
        public array $shouldBeCamelCasePhpdocProperty = [],
        public array $shouldBeCamelCaseRelationship = [],
        public array $addPropertyRead = [],
        public array $morphToRelationships = [],
        public array $modelEvidenceConflicts = [],
    ) {}

    /**
     * Convert the ReportDto to an array with snake cased keys.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [];
        foreach (get_object_vars($this) as $key => $value) {
            $result[Str::snake($key)] = $value;
        }

        return $result;
    }
}
