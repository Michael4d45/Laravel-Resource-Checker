<?php

declare(strict_types=1);

namespace Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\Pipes;

use PhpParser\Node\Stmt\Class_;

class DocBlockHelper
{
    /**
     * Add properties to a class's PHPDoc comment.
     *
     * @param  array<string>  $newProperties
     */
    public function addPropertiesToDocBlock(Class_ $class, string $code, array $newProperties, bool $checkDuplicates = false): string
    {
        $existingDoc = $class->getDocComment();

        if (empty($newProperties)) {
            return $code;
        }

        if ($existingDoc) {
            return $this->updateExistingDocBlock($code, $existingDoc->getText(), $newProperties, $checkDuplicates);
        }

        return $this->createNewDocBlock($code, $class, $newProperties);
    }

    /**
     * Update an existing docblock with new properties.
     *
     * @param  array<string>  $newProperties
     */
    private function updateExistingDocBlock(string $code, string $docText, array $newProperties, bool $checkDuplicates): string
    {
        $docLines = explode("\n", $docText);

        if ($checkDuplicates) {
            $newProperties = $this->filterDuplicateProperties($docLines, $newProperties);
        }

        if (empty($newProperties)) {
            return $code;
        }

        // Find the last @property line in the docblock
        $lastPropertyLineInDoc = count($docLines) - 2; // before */
        for ($i = count($docLines) - 1; $i >= 0; $i--) {
            if (strpos($docLines[$i], '@property') !== false) {
                $lastPropertyLineInDoc = $i;
                break;
            }
        }

        // Insert new properties after the last @property (or before */)
        array_splice($docLines, $lastPropertyLineInDoc + 1, 0, $newProperties);
        $newDocText = implode("\n", $docLines);

        return str_replace($docText, $newDocText, $code);
    }

    /**
     * Create a new docblock for a class that doesn't have one.
     *
     * @param  array<string>  $newProperties
     */
    private function createNewDocBlock(string $code, Class_ $class, array $newProperties): string
    {
        $classLine = $class->getLine() - 1; // getLine returns 1-indexed
        $lines = explode("\n", $code);
        $newDoc = "/**\n";
        foreach ($newProperties as $prop) {
            $newDoc .= $prop . "\n";
        }
        $newDoc .= " */\n";

        // Insert before the class keyword
        $docLines = explode("\n", $newDoc);
        array_splice($lines, $classLine, 0, $docLines);

        return implode("\n", $lines);
    }

    /**
     * Filter out properties that already exist in the docblock.
     *
     * @param  array<string>  $docLines
     * @param  array<string>  $newProperties
     * @return array<string>
     */
    private function filterDuplicateProperties(array $docLines, array $newProperties): array
    {
        $existingProperties = [];
        foreach ($docLines as $line) {
            if (preg_match('/@property(?:-read)?\s+(.+?)\s+\$(.+)/', $line, $matches)) {
                $existingProperties[] = $matches[2];
            }
        }

        return array_filter($newProperties, function ($prop) use ($existingProperties) {
            if (preg_match('/@property(?:-read)?\s+(.+?)\s+\$(.+)/', $prop, $matches)) {
                return ! in_array($matches[2], $existingProperties);
            }

            return true;
        });
    }
}
