<?php

declare(strict_types=1);

namespace Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs;

use Illuminate\Contracts\Support\Arrayable;

/**
 * DTO representing a wrong type found in model PHPDoc compared to migration.
 *
 * @implements Arrayable<string, mixed>
 */
class WrongTypeDto implements Arrayable
{
    public function __construct(
        public string $fieldName,
        public string $expectedType,
        public string $actualType,
        public bool $expectedNullable,
        public bool $actualNullable,
    ) {}

    /**
     * @return array<string, bool|string|null>
     */
    public function toArray(): array
    {
        return [
            'fieldName' => $this->fieldName,
            'expected_type' => $this->expectedType,
            'actual_type' => $this->actualType,
            'expected_nullable' => $this->expectedNullable,
            'actual_nullable' => $this->actualNullable,
        ];
    }
}
