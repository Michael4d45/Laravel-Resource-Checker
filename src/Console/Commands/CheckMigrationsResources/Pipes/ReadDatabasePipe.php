<?php

declare(strict_types=1);

namespace Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\Pipes;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\AnalysisResultDto;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\FieldDto;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\FieldTable;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\ResourceReportDto;

class ReadDatabasePipe
{
    public function __invoke(
        AnalysisResultDto $dto,
        \Closure $next,
    ): AnalysisResultDto {
        $tables = $this->tablesInCurrentConnection();

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
        $mappings = config()->array('migration-resource-checker.column_type_mappings', []);

        $value = $mappings[$doctrineType] ?? null;

        return is_string($value) ? $value : 'mixed';
    }

    /**
     * Table names that belong to this Laravel connection only.
     *
     * MySQL `Schema::getTableListing()` with a null schema lists every
     * non-system database the user can see.
     *
     * @return list<string>
     */
    private function tablesInCurrentConnection(): array
    {
        $schemas = $this->currentSchemas();
        $listing = Schema::getTableListing($schemas, false);
        $allowed = array_fill_keys($schemas, true);
        $tables = [];

        foreach ($listing as $tableName) {
            if (!is_string($tableName) || $tableName === '') {
                continue;
            }

            if (str_contains($tableName, '.')) {
                $parts = explode('.', $tableName);
                $schema = (string) $parts[0];
                $tableName = (string) end($parts);
                if ($tableName === '' || !isset($allowed[$schema])) {
                    continue;
                }
            }

            $tables[] = $tableName;
        }

        return array_values(array_unique($tables));
    }

    /**
     * @return list<string>
     */
    private function currentSchemas(): array
    {
        $schemas = Schema::getCurrentSchemaListing();
        if (is_array($schemas) && $schemas !== []) {
            return array_values(array_filter(
                $schemas,
                static fn (mixed $schema): bool => is_string($schema) && $schema !== '',
            ));
        }

        $database = DB::connection()->getDatabaseName();
        if (is_string($database) && $database !== '') {
            return [$database];
        }

        throw new \RuntimeException(
            'Cannot determine the current database schema; refusing to list tables across all databases.',
        );
    }
}
