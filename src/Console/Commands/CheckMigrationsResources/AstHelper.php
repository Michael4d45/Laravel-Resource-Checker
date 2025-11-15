<?php

declare(strict_types=1);

namespace Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\Parser;
use PhpParser\ParserFactory;

class AstHelper
{
    private Parser $parser;

    private NodeFinder $finder;

    public function __construct()
    {
        $this->parser = (new ParserFactory)->createForHostVersion();
        $this->finder = new NodeFinder;
    }

    /**
     * @return array<Node>|null
     */
    public function parseFile(string $filePath): array|null
    {
        $code = file_get_contents($filePath);
        if ($code === false) {
            return null;
        }

        return $this->parseString($code);
    }

    /**
     * @return array<Node>|null
     */
    public function parseString(string $code): array|null
    {
        return $this->parser->parse($code);
    }

    /**
     * @param  array<Node>  $ast
     */
    public function attachParentReferences(array &$ast): void
    {
        $traverser = new NodeTraverser;
        $traverser->addVisitor(new ParentConnectingVisitor);
        $traverser->traverse($ast);
    }

    public function finder(): NodeFinder
    {
        return $this->finder;
    }

    /**
     * @param  array<Node>  $ast
     */
    public function resolveClassName(string $className, array $ast, string $namespace): string
    {
        $className = ltrim($className, '\\');

        if (str_contains($className, '\\')) {
            return $className;
        }

        $uses = $this->finder->findInstanceOf($ast, Use_::class);
        foreach ($uses as $use) {
            foreach ($use->uses as $useUse) {
                $alias = $useUse->alias !== null ? $useUse->alias->toString() : $useUse->name->getLast();
                if ($alias === $className) {
                    return $useUse->name->toString();
                }
            }
        }

        if ($namespace === '') {
            return $className;
        }

        return $namespace . '\\' . $className;
    }

    /**
     * @param  array<Node>  $ast
     */
    public function getNamespace(array $ast): string
    {
        $namespaces = $this->finder->findInstanceOf($ast, Namespace_::class);
        if (! empty($namespaces)) {
            return $namespaces[0]->name?->toString() ?? '';
        }

        return '';
    }

    /**
     * @param  array<Node>  $ast
     */
    public function getClassName(array $ast): string
    {
        $classes = $this->finder->findInstanceOf($ast, Class_::class);
        if (empty($classes)) {
            return '';
        }

        $class = $classes[0];
        $name = $class->name?->toString();
        if ($name === null) {
            return '';
        }

        $namespace = $this->getNamespace($ast);

        return $namespace === '' ? $name : $namespace . '\\' . $name;
    }

    /**
     * @param  array<Node>  $ast
     * @return array<string, array{type: string, class: string}>
     */
    public function extractRelationships(array $ast, string $namespace): array
    {
        $relationships = [];
        $methods = $this->finder->findInstanceOf($ast, ClassMethod::class);
        foreach ($methods as $method) {
            if (! $method->isPublic()) {
                continue;
            }
            $methodName = $method->name->toString();
            if (in_array($methodName, ['__construct', '__destruct'], true)) {
                continue;
            }
            $stmts = $method->stmts;
            if (! $stmts) {
                continue;
            }
            foreach ($stmts as $stmt) {
                if ($stmt instanceof Return_ && $stmt->expr instanceof MethodCall) {
                    $call = $stmt->expr;
                    while ($call instanceof MethodCall && ! ($call->var instanceof Variable && $call->var->name === 'this')) {
                        $call = $call->var;
                    }

                    if (! ($call instanceof MethodCall && $call->var instanceof Variable && $call->var->name === 'this')) {
                        continue;
                    }

                    $relationType = $call->name instanceof Identifier ? $call->name->toString() : null;
                    if (! $relationType || ! in_array($relationType, ['belongsTo', 'hasOne', 'hasMany', 'belongsToMany', 'morphTo', 'morphOne', 'morphMany', 'morphToMany', 'hasManyThrough'], true)) {
                        continue;
                    }

                    $args = $call->args;
                    if (empty($args) || ! $args[0] instanceof Arg) {
                        continue;
                    }

                    $firstArg = $args[0]->value;
                    if (! ($firstArg instanceof ClassConstFetch && $firstArg->class instanceof Name)) {
                        continue;
                    }

                    $className = $this->resolveClassName($firstArg->class->toString(), $ast, $namespace);
                    $relationships[$methodName] = ['type' => $relationType, 'class' => $className];
                }
            }
        }

        return $relationships;
    }
}
