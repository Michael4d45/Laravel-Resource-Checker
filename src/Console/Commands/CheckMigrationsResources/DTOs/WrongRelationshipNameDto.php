<?php

declare(strict_types=1);

namespace Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs;

class WrongRelationshipNameDto
{
    public function __construct(
        public string $relationshipName,
        public string $expectedName,
    ) {}
}
