<?php

declare(strict_types=1);

namespace Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\Pipes;

use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\AnalysisResultDto;

class FixWrongPropertyReadPipe extends BaseFixerPipe
{
    public function __invoke(AnalysisResultDto $dto, \Closure $next): AnalysisResultDto
    {
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

                    foreach ($docLines as &$line) {
                        if (preg_match('/@property-read\s+(.+?)\s+\$(.+)/', $line, $matches)) {
                            $propName = $matches[2];
                            if (! preg_match('/^[a-z][a-zA-Z0-9]*$/', $propName)) {
                                // Find the correct name from relationships
                                $correctName = null;
                                foreach (($dto->resources[$table])->modelRelationships ?? [] as $relName => $relData) {
                                    if (str($propName)->camel()->toString() === $relName || str($propName)->snake()->toString() === $relName) {
                                        $correctName = $relName;
                                        break;
                                    }
                                }
                                if ($correctName) {
                                    $line = str_replace("\${$propName}", "\${$correctName}", $line);
                                    $changed = true;
                                }
                            }
                        }
                    }

                    if ($changed) {
                        $newDocText = implode("\n", $docLines);
                        $code = str_replace($docText, $newDocText, $code);
                        if ($this->writeFile($filePath, $code)) {
                            $this->command->info("Fixed wrong @property-read names in {$filePath}");
                        }
                    }
                }
            } catch (\Throwable $e) {
                $this->command->error("Failed to fix {$filePath}: " . $e->getMessage());
            }
        }

        return $next($dto);
    }
}
