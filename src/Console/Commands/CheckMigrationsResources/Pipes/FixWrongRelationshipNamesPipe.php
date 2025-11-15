<?php

declare(strict_types=1);

namespace Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\Pipes;

use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\AnalysisResultDto;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\FieldTable;

class FixWrongRelationshipNamesPipe extends BaseFixerPipe
{
    public function __invoke(AnalysisResultDto $dto, \Closure $next): AnalysisResultDto
    {
        if (empty($dto->report)) {
            return $next($dto);
        }
        /** @var array<string, FieldTable> $wrongRels */
        $wrongRels = $dto->report->shouldBeCamelCaseRelationship;
        $modelFilePaths = $dto->modelFilePaths;
        foreach ($wrongRels as $table => $fieldTableDto) {
            if (! isset($modelFilePaths[$table])) {
                $this->command->warn("Model file for table {$table} not found.");

                continue;
            }

            $filePath = (string) $modelFilePaths[$table];

            try {
                $code = $this->readFile($filePath);
                if ($code === null) {
                    continue;
                }

                $changed = false;
                foreach ($fieldTableDto as $fieldDto) {
                    $wrongRel = $fieldDto->name;
                    $newRel = str($wrongRel)->camel()->toString();
                    // Simple string replacement for method name
                    // This is risky as it might replace other occurrences, but for a fix, it's a start
                    $result = preg_replace('/\bfunction\s+' . preg_quote($wrongRel, '/') . '\s*\(/', 'function ' . $newRel . '(', $code);
                    if ($result !== null) {
                        $code = $result;
                        $changed = true;
                    }
                }

                if ($changed && $this->writeFile($filePath, $code)) {
                    $this->command->info('Fixed ' . $fieldTableDto->count() . " wrong relationship names in {$filePath}");
                }
            } catch (\Throwable $e) {
                $this->command->error("Failed to fix {$filePath}: " . $e->getMessage());
            }
        }

        return $next($dto);
    }
}
