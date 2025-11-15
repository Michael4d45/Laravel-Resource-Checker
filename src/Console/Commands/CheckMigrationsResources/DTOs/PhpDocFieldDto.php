<?php

declare(strict_types=1);

namespace Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, bool|string|null>
 */
class PhpDocFieldDto implements Arrayable
{
    public function __construct(
        public string $name,
        public string $type,
        public bool $nullable,
        public string|null $arrayType = null,
        public string|null $keyType = null,
    ) {}

    /**
     * @return array<string, bool|string|null>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'nullable' => $this->nullable,
            'array_type' => $this->arrayType,
            'key_type' => $this->keyType,
        ];
    }
}
