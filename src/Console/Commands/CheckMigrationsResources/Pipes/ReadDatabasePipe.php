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
            // Strip database name if present (e.g., "database.table" -> "table")
            $cleanTableName = $tableName;
            if (str_contains($tableName, '.')) {
                $parts = explode('.', $tableName);
                $cleanTableName = end($parts);
            }
            $columns = Schema::getColumns($cleanTableName);
            $columnInfos = [];
            foreach ($columns as $column) {
                $name = $column['name'];
                $type = $this->mapDoctrineTypeToLaravel($column['type_name']);
                $nullable = $column['nullable'];
                $columnInfos[$name] = new FieldDto($name, $type, $nullable);
            }
            $migrationTables[$cleanTableName] = new FieldTable($columnInfos);
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
        $mappings = config()->array('migration-resource-checker.column_type_mappings', []);

        $value = $mappings[$doctrineType] ?? null;

        return is_string($value) ? $value : 'mixed';
    }
}
