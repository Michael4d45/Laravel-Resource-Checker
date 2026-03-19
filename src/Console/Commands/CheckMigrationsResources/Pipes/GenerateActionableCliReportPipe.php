<?php

declare(strict_types=1);

namespace Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\Pipes;

use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\AnalysisResultDto;

class GenerateActionableCliReportPipe
{
    public function __invoke(
        AnalysisResultDto $dto,
        \Closure $next,
    ): AnalysisResultDto {
        $report = $dto->report->toArray();
        $dto->actionableReport = $this->filterActionableReport($report);

        return $next($dto);
    }

    /**
     * Keep only actionable report sections for CLI output.
     *
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function filterActionableReport(array $report): array
    {
        $actionable = [];

        foreach ($report as $key => $value) {
            if ($key === 'morph_to_relationships') {
                $morphToRelationships =
                    $this->normalizeMorphToRelationships($value);

                if ($morphToRelationships !== null) {
                    $value = $this->filterActionableMorphToRelationships(
                        $morphToRelationships,
                    );
                }
            }

            if (!$this->isActionableValue($value)) {
                continue;
            }

            $actionable[$key] = $value;
        }

        return $actionable;
    }

    /**
     * @param  array<string, mixed>  $morphToRelationships
     * @return array<string, mixed>
     */
    private function filterActionableMorphToRelationships(array $morphToRelationships): array
    {
        $result = [];

        foreach ($morphToRelationships as $table => $relationships) {
            if (!is_array($relationships)) {
                continue;
            }

            $actionableRelationships = [];
            foreach ($relationships as $relationshipName => $details) {
                if (!is_array($details)) {
                    continue;
                }

                $hasPhpDocRead =
                    ($details['has_phpdoc_read'] ?? false) === true;
                $phpDocType = $details['phpdoc_type'] ?? null;
                $phpDocTypeMissing =
                    !is_string($phpDocType) || trim($phpDocType) === '';

                if ($hasPhpDocRead && !$phpDocTypeMissing) {
                    continue;
                }

                $actionableRelationships[$relationshipName] = $details;
            }

            if ($actionableRelationships !== []) {
                $result[$table] = $actionableRelationships;
            }
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeMorphToRelationships(mixed $value): null|array
    {
        if (!is_array($value)) {
            return null;
        }

        $normalized = [];
        foreach ($value as $table => $relationships) {
            if (!is_string($table)) {
                continue;
            }

            $normalized[$table] = $relationships;
        }

        return $normalized;
    }

    private function isActionableValue(mixed $value): bool
    {
        if (is_array($value)) {
            return $value !== [];
        }

        return $value !== null;
    }
}
