<?php

declare(strict_types=1);

namespace Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\Pipes;

use Illuminate\Console\Command;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\AnalysisResultDto;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\FieldTable;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\RelationshipFieldTable;

class FixMissingPropertyReadPipe extends BaseFixerPipe
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
        /** @var array<string, FieldTable> $addPropertyRead */
        $addPropertyRead = $dto->report->addPropertyRead;
        $modelFilePaths = $dto->modelFilePaths;

        foreach ($addPropertyRead as $table => $fieldTableDto) {
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
                    $rel = $fieldDto->name;
                    // Find the corresponding relationship data
                    $relData = null;
                    $relationshipTable = $dto->resources[$table]->modelRelationships ?? new RelationshipFieldTable;
                    foreach ($relationshipTable as $relName => $data) {
                        if ($relName === $rel) {
                            $relData = $data;
                            break;
                        }
                    }
                    if ($relData === null) {
                        continue;
                    }
                    // RelationshipFieldDto now contains a short relation type in ->type
                    // and the related model class in ->model. Prefer the model from the
                    // relationship DTO as the canonical source of truth.
                    $type = $relData->type;
                    $className = $relData->model;

                    if (str_starts_with($type, 'BelongsTo') || str_starts_with($type, 'HasOne') || str_starts_with($type, 'MorphTo') || str_starts_with($type, 'MorphOne')) {
                        $phpType = '?\\' . $className;
                    } elseif (str_starts_with($type, 'HasMany') || str_starts_with($type, 'BelongsToMany') || str_starts_with($type, 'MorphMany') || str_starts_with($type, 'MorphToMany') || str_starts_with($type, 'HasManyThrough')) {
                        $phpType = '\Illuminate\Database\Eloquent\Collection<int, \\' . $className . '>';
                    } else {
                        $phpType = 'mixed';
                    }

                    $newProperties[] = " * @property-read {$phpType} \${$rel}";
                }

                $code = $this->docBlockHelper->addPropertiesToDocBlock($parsed['class'], $code, $newProperties, true);

                if ($this->writeFile($filePath, $code)) {
                    $this->command->info('Added ' . $fieldTableDto->count() . " missing @property-read annotations to {$filePath}");
                }
            } catch (\Throwable $e) {
                $this->command->error("Failed to fix {$filePath}: " . $e->getMessage());
            }
        }

        return $next($dto);
    }

    // NOTE: Class extraction from the relation return type is no longer
    // required since RelationshipFieldDto now provides the related model
    // explicitly via ->model.
}
