<?php

declare(strict_types=1);

namespace Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\Pipes;

use Illuminate\Console\Command;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\AstHelper;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;

abstract class BaseFixerPipe
{
    protected AstHelper $astHelper;

    public function __construct(protected Command $command)
    {
        $this->astHelper = new AstHelper;
    }

    /**
     * Parse a PHP file and return the AST and class node.
     *
     * @return array{ast: array<Node>, class: Class_}|null
     */
    protected function parseFile(string $filePath): array|null
    {
        $ast = $this->astHelper->parseFile($filePath);
        if ($ast === null) {
            $this->command->error("Failed to parse {$filePath}");

            return null;
        }

        $finder = $this->astHelper->finder();
        /** @var array<Class_> $classes */
        $classes = $finder->findInstanceOf($ast, Class_::class);
        if (empty($classes)) {
            $this->command->warn("Could not find class in {$filePath}");

            return null;
        }

        return [
            'ast' => $ast,
            'class' => $classes[0],
        ];
    }

    /**
     * Safely read a file.
     */
    protected function readFile(string $filePath): string|null
    {
        $code = file_get_contents($filePath);
        if ($code === false) {
            $this->command->error("Failed to read {$filePath}");

            return null;
        }

        return $code;
    }

    /**
     * Safely write a file.
     */
    protected function writeFile(string $filePath, string $content): bool
    {
        $result = file_put_contents($filePath, $content);
        if ($result === false) {
            $this->command->error("Failed to write {$filePath}");

            return false;
        }

        return true;
    }
}
