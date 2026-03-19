<?php

declare(strict_types=1);

namespace Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\Pipes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\AstHelper;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\AnalysisResultDto;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\ResourceReportDto;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\Services\DocBlockTypeExtractor;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\Services\SurveyorModelAnalyzer;
use PhpParser\Node\Stmt\Class_;

class ParseModelsPipe
{
    private AstHelper $astHelper;
    private SurveyorModelAnalyzer $modelAnalyzer;
    private DocBlockTypeExtractor $docBlockTypeExtractor;

    public function __construct()
    {
        $this->astHelper = new AstHelper;
        $this->modelAnalyzer = app(SurveyorModelAnalyzer::class);
        $this->docBlockTypeExtractor = app(DocBlockTypeExtractor::class);
    }

    public function __invoke(
        AnalysisResultDto $dto,
        \Closure $next,
    ): AnalysisResultDto {
        $modelsDir =
            base_path()
            . DIRECTORY_SEPARATOR
            . 'app'
            . DIRECTORY_SEPARATOR
            . 'Models';
        $modelFiles = [];

        if (is_dir($modelsDir)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($modelsDir),
            );

            foreach ($iterator as $file) {
                if (
                    !(
                        $file instanceof \SplFileInfo
                        && $file->isFile()
                        && $file->getExtension() === 'php'
                    )
                ) {
                    continue;
                }

                $modelFiles[] = $file->getPathname();
            }
        }

        $resources = $dto->resources;
        $modelFilePaths = [];
        $phpdocAttributes = [];
        $phpdocRead = [];

        foreach ($modelFiles as $modelFile) {
            try {
                $modelFile = (string) $modelFile;
                $ast = $this->astHelper->parseFile($modelFile);
                if ($ast === null) {
                    throw new \RuntimeException(
                        "Failed to parse model file: {$modelFile}",
                    );
                }

                $className = $this->astHelper->getClassName($ast);
                if ($className === '') {
                    continue;
                }

                $model = new $className;
                if (!$model instanceof Model) {
                    continue;
                }

                $namespace = $this->astHelper->getNamespace($ast);
                $analysis = $this->modelAnalyzer->analyze(
                    $className,
                    $ast,
                    $namespace,
                );
                $tableName = $analysis->tableName;

                $modelFilePaths[$tableName] = $modelFile;

                $resourceReport =
                    $resources[$tableName] ?? new ResourceReportDto;
                $resourceReport->modelFields = $analysis->modelFields;
                $resourceReport->modelRelationships =
                    $analysis->modelRelationships;
                $resources[$tableName] = $resourceReport;

                if (!$this->docBlockEnabled()) {
                    continue;
                }

                /** @var array<Class_> $classes */
                $classes = $this->astHelper->finder()->findInstanceOf(
                    $ast,
                    Class_::class,
                );
                if ($classes === []) {
                    continue;
                }

                $doc = $classes[0]->getDocComment();
                if ($doc === null) {
                    continue;
                }

                $extracted = $this->docBlockTypeExtractor->extract(
                    $doc->getText(),
                    $ast,
                    $namespace,
                );

                $phpdocAttributes[$tableName] = $extracted['properties'];
                $phpdocRead[$tableName] = $extracted['read'];
            } catch (\Throwable $exception) {
                Log::warning('Failed to analyze model file for check:migrations-resources.', [
                    'file' => $modelFile,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        foreach ($phpdocAttributes as $table => $fields) {
            $resourceReport = $resources[$table] ?? new ResourceReportDto;
            $resourceReport->phpdocFields = $fields;
            $resources[$table] = $resourceReport;
        }

        foreach ($phpdocRead as $table => $fields) {
            $resourceReport = $resources[$table] ?? new ResourceReportDto;
            $resourceReport->phpdocReadFields = $fields;
            $resources[$table] = $resourceReport;
        }

        $dto->resources = $resources;
        $dto->modelFilePaths = $modelFilePaths;

        return $next($dto);
    }

    private function docBlockEnabled(): bool
    {
        return (bool) config(
            'migration-resource-checker.docblock_enrichment',
            true,
        );
    }
}
