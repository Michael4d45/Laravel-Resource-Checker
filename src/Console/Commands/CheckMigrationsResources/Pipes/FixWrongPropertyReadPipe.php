<?php

declare(strict_types=1);

namespace Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\Pipes;

use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\AnalysisResultDto;

class FixWrongPropertyReadPipe extends BaseFixerPipe
{
    public function __invoke(
        AnalysisResultDto $dto,
        \Closure $next,
    ): AnalysisResultDto {
        $modelFilePaths = $dto->modelFilePaths;
        foreach ($modelFilePaths as $table => $filePath) {
            $filePath = (string) $filePath;

            try {
                $parsed = $this->parseFile($filePath);
                if ($parsed === null) {
                    continue;
                }

                $code = $this->readFile($filePath);
                if ($code === null) {
                    continue;
                }

                $existingDoc = $parsed['class']->getDocComment();

                if ($existingDoc) {
                    $docText = $existingDoc->getText();
                    $docLines = explode("\n", $docText);
                    $changed = false;
                    $relationships = $dto->resources[$table]->modelRelationships
                    ?? [];

                    foreach ($docLines as &$line) {
                        if (!preg_match(
                            '/@property-read\s+(.+?)\s+\$(.+)/',
                            $line,
                            $matches,
                        )) {
                            continue;
                        }

                        $propName = $matches[2];
                        if (preg_match('/^[a-z][a-zA-Z0-9]*$/', $propName)) {
                            continue;
                        }

                        $correctName = $this->resolveCorrectRelationshipName(
                            $propName,
                            $relationships,
                        );
                        if ($correctName === null) {
                            continue;
                        }

                        $line = str_replace(
                            "\${$propName}",
                            "\${$correctName}",
                            $line,
                        );
                        $changed = true;
                    }

                    if ($changed) {
                        $newDocText = implode("\n", $docLines);
                        $code = str_replace($docText, $newDocText, $code);
                        if ($this->writeFile($filePath, $code)) {
                            $this->command->info(
                                "Fixed wrong @property-read names in {$filePath}",
                            );
                        }
                    }
                }
            } catch (\Throwable $e) {
                $this->command->error(
                    "Failed to fix {$filePath}: " . $e->getMessage(),
                );
            }
        }

        return $next($dto);
    }

    /**
     * @param  iterable<string, mixed>  $relationships
     */
    private function resolveCorrectRelationshipName(
        string $propertyName,
        iterable $relationships,
    ): string|null {
        $camelName = str($propertyName)->camel()->toString();
        $snakeName = str($propertyName)->snake()->toString();

        foreach ($relationships as $relationshipName => $relationshipData) {
            if (
                $camelName !== $relationshipName
                && $snakeName !== $relationshipName
            ) {
                continue;
            }

            return $relationshipName;
        }

        return null;
    }
}
