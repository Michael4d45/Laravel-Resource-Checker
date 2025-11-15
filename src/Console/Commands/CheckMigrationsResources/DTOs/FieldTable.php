<?php

declare(strict_types=1);

namespace Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs;

use Illuminate\Support\Collection;

/**
 * @extends Collection<string, FieldDto>
 */
class FieldTable extends Collection
{
    /**
     * @param  array<string, FieldDto>  $columns
     */
    public function __construct(array $columns = [])
    {
        parent::__construct($columns);
    }
}
