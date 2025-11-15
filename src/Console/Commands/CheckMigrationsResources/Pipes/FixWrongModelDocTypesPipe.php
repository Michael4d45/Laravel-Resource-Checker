<?php

declare(strict_types=1);

namespace Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\Pipes;

use Illuminate\Console\Command;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\AnalysisResultDto;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\WrongTypeDto;
use PhpParser\Node\Stmt\Class_;

class FixWrongModelDocTypesPipe extends BaseFixerPipe
{
    public function __construct(Command $command)
    {
        parent::__construct($command);
    }

    public function __invoke(AnalysisResultDto $dto, \Closure $next): AnalysisResultDto
    {
        if (empty($dto->report)) {
            return $next($dto);
        }
        /** @var array<string, array<string, WrongTypeDto>> $wrongTypes */
        $wrongTypes = $dto->report->wrongModelDocTypes;
        $modelFilePaths = $dto->modelFilePaths;

        foreach ($wrongTypes as $table => $fieldTypes) {
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

                $propertiesToUpdate = [];
                foreach ($fieldTypes as $fieldName => $wrongTypeDto) {
                    $expectedType = $wrongTypeDto->expectedType;
                    $expectedNullable = $wrongTypeDto->expectedNullable;

                    // Add nullable suffix using union style (Type|null)
                    if ($expectedNullable) {
                        $expectedType .= '|null';
                    }

                    $propertiesToUpdate[] = " * @property {$expectedType} \${$fieldName}";
                }

                if (! empty($propertiesToUpdate)) {
                    $code = $this->updateExistingProperties($code, $parsed['class'], $propertiesToUpdate, $fieldTypes);
                }

                if ($this->writeFile($filePath, $code)) {
                    $this->command->info('Fixed ' . count($propertiesToUpdate) . " wrong PHPDoc types in {$filePath}");
                }
            } catch (\Throwable $e) {
                $this->command->error("Failed to fix {$filePath}: " . $e->getMessage());
            }
        }

        return $next($dto);
    }

    /**
     * Update existing @property annotations with correct types.
     *
     * @param  array<string>  $propertiesToUpdate
     * @param  array<string, WrongTypeDto>  $fieldTypes
     */
    private function updateExistingProperties(string $code, Class_ $class, array $propertiesToUpdate, array $fieldTypes): string
    {
        $existingDoc = $class->getDocComment();

        if (! $existingDoc) {
            return $code;
        }

        $docText = $existingDoc->getText();
        $docLines = explode("\n", $docText);

        $updated = false;
        foreach ($docLines as &$line) {
            // Look for @property lines
            if (preg_match('/^\s*\*\s*@property\s+(.+?)\s+\$([a-zA-Z0-9_]+)/', $line, $matches)) {
                $currentType = trim($matches[1]);
                $fieldName = $matches[2];

                if (isset($fieldTypes[$fieldName])) {
                    $expectedType = $fieldTypes[$fieldName]->expectedType;
                    $expectedNullable = $fieldTypes[$fieldName]->expectedNullable;

                    // Add nullable suffix using union style (Type|null)
                    if ($expectedNullable) {
                        $expectedType .= '|null';
                    }

                    // Replace the type in the line
                    $line = str_replace($currentType, $expectedType, $line);
                    $updated = true;
                }
            }
        }

        if ($updated) {
            $newDocText = implode("\n", $docLines);

            return str_replace($docText, $newDocText, $code);
        }

        return $code;
    }
}
