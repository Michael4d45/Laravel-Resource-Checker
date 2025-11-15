<?php

declare(strict_types=1);

namespace Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\Pipes;

use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\AnalysisResultDto;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\FieldTable;
use Illuminate\Console\Command;

class FixMissingPropertiesPipe extends BaseFixerPipe
{
    private DocBlockHelper $docBlockHelper;

    public function __construct(Command $command)
    {
        parent::__construct($command);
        $this->docBlockHelper = new DocBlockHelper;
    }

    public function __invoke(AnalysisResultDto $dto, \Closure $next): AnalysisResultDto
    {
        if (empty($dto->report)) {
            return $next($dto);
        }
        /** @var array<string, FieldTable> $addFields */
        $addFields = $dto->report->addFieldsToModelDocs;
        $modelFilePaths = $dto->modelFilePaths;

        foreach ($addFields as $table => $fieldTableDto) {
            if (! isset($modelFilePaths[$table])) {
                $this->command->warn("Model file for table {$table} not found.");

                continue;
            }

            $filePath = (string) $modelFilePaths[$table];

            try {
                $parsed = $this->parseFile($filePath);
                if ($parsed === null) {
                    continue;
                }

                $code = $this->readFile($filePath);
                if ($code === null) {
                    continue;
                }

                // Build new properties to add
                $newProperties = [];
                foreach ($fieldTableDto as $fieldDto) {
                    $field = $fieldDto->name;
                    $type = $fieldDto->type;
                    $nullable = $fieldDto->nullable;

                    // Use full namespace for Carbon
                    if ($type === 'Carbon') {
                        $type = '\\Illuminate\\Support\\Carbon';
                    }

                    if ($type === 'Point') {
                        $type = '\\Clickbar\\Magellan\\Data\\Geometries\\Point';
                    }

                    if ($type === 'Box2D') {
                        $type = '\\Clickbar\\Magellan\\Data\\Boxes\\Box2D';
                    }

                    // Add nullable suffix using union style (Type|null)
                    if ($nullable) {
                        $type .= '|null';
                    }

                    $newProperties[] = " * @property {$type} \${$field}";
                }

                $code = $this->docBlockHelper->addPropertiesToDocBlock($parsed['class'], $code, $newProperties);

                if ($this->writeFile($filePath, $code)) {
                    $this->command->info('Added ' . $fieldTableDto->count() . " missing properties to {$filePath}");
                }
            } catch (\Throwable $e) {
                $this->command->error("Failed to fix {$filePath}: " . $e->getMessage());
            }
        }

        return $next($dto);
    }
}
