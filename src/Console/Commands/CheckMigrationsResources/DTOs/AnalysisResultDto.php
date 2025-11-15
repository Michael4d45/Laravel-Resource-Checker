<?php

declare(strict_types=1);

namespace Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs;

class AnalysisResultDto
{
    /**
     * @param  array<string, string>  $modelFilePaths
     * @param  array<string, string>  $filamentResourceModelMap
     * @param  array<string, ResourceReportDto>  $resources
     * @param  array<string, FieldTable>  $migrations
     */
    public function __construct(
        public ReportDto $report,
        public array $modelFilePaths = [],
        public array $filamentResourceModelMap = [],
        public array $resources = [],
        public array $migrations = [],
    ) {}
}
