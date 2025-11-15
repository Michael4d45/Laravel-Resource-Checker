<?php

declare(strict_types=1);

namespace Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs;

class ModelFieldDto
{
    public function __construct(
        public string $name,
        public string|null $cast = null,
        public bool $fillable = false,
        public bool $hidden = false,
    ) {}
}
