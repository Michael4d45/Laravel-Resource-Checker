<?php

declare(strict_types=1);

namespace Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs;

/**
 * A relationship @property-read whose documented item type does not match the related model.
 * Plural relationships (HasMany, MorphMany, …) may use the related model class alone; a Collection generic is optional.
 */
final class WrongRelationshipPhpdocReadDto implements \JsonSerializable
{
    /**
     * @param  list<string>  $issueCodes
     */
    public function __construct(
        public string $relationshipName,
        public string $relationshipType,
        public string $relatedModel,
        public string $phpdocType,
        public bool $nullable,
        public array $issueCodes,
        public string $summary,
        public string $suggestedPhpdocType,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'relationship_name' => $this->relationshipName,
            'relationship_type' => $this->relationshipType,
            'related_model' => $this->relatedModel,
            'phpdoc_type' => $this->phpdocType,
            'nullable' => $this->nullable,
            'issue_codes' => $this->issueCodes,
            'summary' => $this->summary,
            'suggested_phpdoc_type' => $this->suggestedPhpdocType,
        ];
    }
}
