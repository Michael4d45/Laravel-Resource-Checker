<?php

declare(strict_types=1);

namespace Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs;

use Illuminate\Support\Collection;

/**
 * @extends Collection<string, ModelFieldDto>
 */
class ModelFieldTable extends Collection
{
    /**
     * @param  array<string, ModelFieldDto>  $columns
     */
    public function __construct(array $columns = [])
    {
        parent::__construct($columns);
    }
}
