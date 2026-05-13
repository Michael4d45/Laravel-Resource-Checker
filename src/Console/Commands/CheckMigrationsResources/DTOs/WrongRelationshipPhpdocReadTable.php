<?php

declare(strict_types=1);

namespace Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs;

use Illuminate\Support\Collection;

/**
 * @extends Collection<string, WrongRelationshipPhpdocReadDto>
 */
class WrongRelationshipPhpdocReadTable extends Collection
{
    /**
     * @param  array<string, WrongRelationshipPhpdocReadDto>  $items
     */
    public function __construct(array $items = [])
    {
        parent::__construct($items);
    }
}
