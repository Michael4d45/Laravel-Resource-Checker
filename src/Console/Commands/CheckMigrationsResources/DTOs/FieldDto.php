<?php

declare(strict_types=1);

namespace Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs;

class FieldDto
{
    public function __construct(
        public string $name,
        public string $type,
        public bool $nullable,
    ) {}
}
