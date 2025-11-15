<?php

declare(strict_types=1);

namespace Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\Pipes;

use Illuminate\Support\Facades\Schema;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\AnalysisResultDto;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\FieldDto;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\FieldTable;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\ResourceReportDto;

class ReadDatabasePipe
{
    public function __invoke(AnalysisResultDto $dto, \Closure $next): AnalysisResultDto
    {
        $tables = Schema::getTableListing();

        $migrationTables = [];
        foreach ($tables as $tableName) {
            $columns = Schema::getColumns($tableName);
            $columnInfos = [];
            foreach ($columns as $column) {
                $name = $column['name'];
                $type = $this->mapDoctrineTypeToLaravel($column['type_name']);
                $nullable = $column['nullable'];
                $columnInfos[$name] = new FieldDto($name, $type, $nullable);
            }
            $migrationTables[$tableName] = new FieldTable($columnInfos);
        }

        $resources = $dto->resources;
        foreach ($migrationTables as $table => $fields) {
            $resourceReport = $resources[$table] ?? new ResourceReportDto;
            $resourceReport->migrationFields = $fields;
            $resources[$table] = $resourceReport;
        }
        $dto->resources = $resources;

        return $next($dto);
    }

    private function mapDoctrineTypeToLaravel(string $doctrineType): string
    {
        // Map Doctrine types to Laravel migration types
        $mappings = [
            'string' => 'string',
            'text' => 'text',
            'integer' => 'int',
            'bigint' => 'bigint',
            'smallint' => 'smallint',
            'boolean' => 'boolean',
            'decimal' => 'decimal',
            'float' => 'float',
            'double' => 'double',
            'datetime' => 'datetime',
            'date' => 'date',
            'time' => 'time',
            'timestamp' => 'timestamp',
            'json' => 'json',
            'binary' => 'binary',
            'uuid' => 'uuid',
        ];

        return $mappings[$doctrineType] ?? 'mixed';
    }
}
