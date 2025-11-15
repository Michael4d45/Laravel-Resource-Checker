<?php

declare(strict_types=1);

namespace Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;

class ResourceReportDto
{
    public function __construct(
        public FieldTable $migrationFields = new FieldTable,
        public ModelFieldTable $modelFields = new ModelFieldTable,
        public FieldTable $filamentFormFields = new FieldTable,
        public PhpDocFieldTable $phpdocFields = new PhpDocFieldTable,
        public PhpDocFieldTable $phpdocReadFields = new PhpDocFieldTable,
        public RelationshipFieldTable $modelRelationships = new RelationshipFieldTable,
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
            if ($value instanceof Arrayable) {
                $value = $value->toArray();
            }
            $result[Str::snake($key)] = $value;
        }

        return $result;
    }
}
