<?php

declare(strict_types=1);

namespace Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs;

class RelationshipFieldDto
{
    public function __construct(
        public string $name,
        public string $type,
        public string $model,
    ) {}
}
