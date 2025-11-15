<?php

declare(strict_types=1);

namespace Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\Pipes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\AstHelper;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\AnalysisResultDto;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\ModelFieldDto;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\ModelFieldTable;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\PhpDocFieldDto;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\PhpDocFieldTable;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\RelationshipFieldDto;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\RelationshipFieldTable;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\ResourceReportDto;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;

class ParseModelsPipe
{
    private AstHelper $astHelper;

    public function __construct()
    {
        $this->astHelper = new AstHelper;
    }

    public function __invoke(AnalysisResultDto $dto, \Closure $next): AnalysisResultDto
    {
        $modelsDir = base_path() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Models';
        $modelFiles = [];
        if (is_dir($modelsDir)) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($modelsDir));
            foreach ($it as $f) {
                if ($f instanceof \SplFileInfo && $f->isFile() && $f->getExtension() === 'php') {
                    $modelFiles[] = $f->getPathname();
                }
            }
        }

        $modelAttributes = [];
        $phpdocAttributes = [];
        $phpdocRead = [];
        $modelFilePaths = [];
        $modelRelationships = [];

        foreach ($modelFiles as $mf) {
            try {
                $mf = (string) $mf;
                $ast = $this->astHelper->parseFile($mf);
                if ($ast === null) {
                    throw new \RuntimeException("Failed to parse model file: {$mf}");
                }

                $className = $this->astHelper->getClassName($ast);
                if (! $className) {
                    continue;
                }

                $model = new $className;
                if (! $model instanceof Model) {
                    continue;
                }

                $tableName = $model->getTable();
                $modelFilePaths[$tableName] = (string) $mf;

                $fields = [];
                foreach ($model->getFillable() as $fieldName) {
                    $fields[$fieldName] = new ModelFieldDto(
                        name: $fieldName,
                        fillable: true,
                    );
                }
                foreach ($model->getHidden() as $fieldName) {
                    if (isset($fields[$fieldName])) {
                        $fields[$fieldName]->hidden = true;
                    } else {
                        $fields[$fieldName] = new ModelFieldDto(
                            name: $fieldName,
                            hidden: true,
                        );
                    }
                }
                foreach ($model->getCasts() as $fieldName => $castType) {
                    if (isset($fields[$fieldName])) {
                        $fields[$fieldName]->cast = $castType;
                    } else {
                        $fields[$fieldName] = new ModelFieldDto(
                            name: $fieldName,
                            cast: $castType,
                        );
                    }
                }

                $modelAttributes[$tableName] = new ModelFieldTable($fields);
                $namespace = $this->astHelper->getNamespace($ast);

                // Extract PHPDoc properties
                /** @var array<Class_> $classes */
                $classes = $this->astHelper->finder()->findInstanceOf($ast, Class_::class);
                if (! empty($classes)) {
                    $class = $classes[0];
                    $doc = $class->getDocComment();
                    if ($doc) {
                        $extracted = $this->extractPhpDocProperties(
                            $doc->getText(),
                            $ast,
                            $namespace,
                        );
                        $phpdocAttributes[$tableName] = $extracted['properties'];
                        $phpdocRead[$tableName] = $extracted['read'];
                    }
                }

                $modelRelationships[$tableName] = $this->extractRelationships($ast, $namespace);
            } catch (\Throwable $e) {
                Log::warning('Failed to analyze model file for check:migrations-resources.', [
                    'file' => $mf,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $dto->modelFilePaths = $modelFilePaths;

        $resources = $dto->resources;
        foreach ($modelAttributes as $table => $fields) {
            $resourceReport = $resources[$table] ?? new ResourceReportDto;
            $resourceReport->modelFields = $fields;
            $resources[$table] = $resourceReport;
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
        foreach ($modelRelationships as $table => $fields) {
            $resourceReport = $resources[$table] ?? new ResourceReportDto;
            $resourceReport->modelRelationships = $fields;
            $resources[$table] = $resourceReport;
        }
        $dto->resources = $resources;

        return $next($dto);
    }

    /**
     * @param  array<Node>  $ast
     * @return array{properties: PhpDocFieldTable, read: PhpDocFieldTable}
     */
    private function extractPhpDocProperties(string $docComment, array $ast, string $namespace): array
    {
        $properties = [];
        $read = [];
        $lines = explode("\n", $docComment);
        foreach ($lines as $line) {
            $parsed = $this->parsePhpDocLine($line);

            if ($parsed === null) {
                continue;
            }

            [$propType, $varType, $name] = $parsed;
            [$varType, $nullable] = $this->getTypeFromComment($varType);
            [$varType, $arrayType, $keyType] = $this->determineArrayInfo($varType);

            if ($this->isProbablyClassType($varType)) {
                $varType = $this->astHelper->resolveClassName($varType, $ast, $namespace);
            }

            $fieldDto = new PhpDocFieldDto($name, $varType, $nullable, $arrayType, $keyType);

            if ($propType === 'property-read') {
                $read[$name] = $fieldDto;

                continue;
            }

            $properties[$name] = $fieldDto;
        }

        return [
            'properties' => new PhpDocFieldTable($properties),
            'read' => new PhpDocFieldTable($read),
        ];
    }

    /**
     * Extract the return type from a PHPDoc comment.
     */
    private function extractReturnType(string $docComment): string
    {
        $lines = explode("\n", $docComment);
        foreach ($lines as $line) {
            $lineProcessed = preg_replace('/^\s*\*\s*/', '', $line);
            if ($lineProcessed === null) {
                $lineProcessed = $line;
            }
            $lineProcessed = trim($lineProcessed);
            // Match @return followed by type
            if (preg_match('/@return\s+(.+)/', $lineProcessed, $matches)) {
                return trim($matches[1]);
            }
        }

        return 'mixed';
    }

    /**
     * Make the return type fully qualified by replacing the model class with its full name.
     */
    private function makeReturnTypeFullQualified(string $returnType, string $fullClassName): string
    {
        // Extract the short class name from the full one
        $shortClassName = basename(str_replace('\\', '/', $fullClassName));

        // Replace the short class name in the return type with the full one
        // Assuming it's in the form RelationType<ShortClass,...>
        if (preg_match('/^([A-Z][a-zA-Z]+)<([^,>]+)(.*)$/', $returnType, $matches)) {
            if ($matches[2] === $shortClassName) {
                return $matches[1] . '<' . $fullClassName . $matches[3];
            }
        }

        return $returnType;
    }

    /**
     * @param  array<Node>  $ast
     */
    private function extractRelationships(array $ast, string $namespace): RelationshipFieldTable
    {
        $relationships = [];
        $methods = $this->astHelper->finder()->findInstanceOf($ast, ClassMethod::class);
        foreach ($methods as $method) {
            if (! $method->isPublic()) {
                continue;
            }
            $methodName = $method->name->toString();
            if (in_array($methodName, ['__construct', '__destruct'])) {
                continue;
            }
            $stmts = $method->stmts;
            if (! $stmts) {
                continue;
            }

            // Extract return type from PHPDoc
            $returnType = 'mixed';
            $doc = $method->getDocComment();
            if ($doc) {
                $returnType = $this->extractReturnType($doc->getText());
            }

            foreach ($stmts as $stmt) {
                if ($stmt instanceof Return_ && $stmt->expr instanceof MethodCall) {
                    $call = $stmt->expr;
                    // Find the root relation call on $this
                    while ($call instanceof MethodCall && ! ($call->var instanceof Variable && $call->var->name === 'this')) {
                        $call = $call->var;
                    }
                    if ($call instanceof MethodCall && $call->var instanceof Variable && $call->var->name === 'this') {
                        $relationType = $call->name instanceof Identifier ? $call->name->toString() : null;
                        if ($relationType && in_array($relationType, ['belongsTo', 'hasOne', 'hasMany', 'belongsToMany', 'morphTo', 'morphOne', 'morphMany', 'morphToMany', 'hasManyThrough'])) {
                            $args = $call->args;
                            if (! empty($args) && $args[0] instanceof Arg) {
                                $className = $this->resolveRelatedClassFromArg($args[0], $ast, $namespace);

                                if ($className === null) {
                                    continue;
                                }

                                $fullReturnType = $this->makeReturnTypeFullQualified($returnType, $className);
                                $relationships[$methodName] = new RelationshipFieldDto(
                                    $methodName,
                                    $this->formatRelationShortName($relationType, $fullReturnType),
                                    $className,
                                );
                            }
                        }
                    }
                }
            }
        }

        return new RelationshipFieldTable($relationships);
    }

    /**
     * @return array{string, string, string}|null
     */
    private function parsePhpDocLine(string $line): array|null
    {
        $lineProcessed = preg_replace('/^\s*\*\s*/', '', $line);
        if ($lineProcessed === null) {
            $lineProcessed = $line;
        }
        $lineProcessed = trim($lineProcessed);

        if (preg_match('/@(property(?:-read|-write)?)\s+(.+?)\s+\$([a-zA-Z0-9_]+)/', $lineProcessed, $matches)) {
            return [$matches[1], $matches[2], $matches[3]];
        }

        return null;
    }

    /**
     * @param  array<Node>  $ast
     */
    private function resolveRelatedClassFromArg(Arg $arg, array $ast, string $namespace): string|null
    {
        $value = $arg->value;

        if ($value instanceof ClassConstFetch && $value->name instanceof Identifier && $value->name->toString() === 'class') {
            if ($value->class instanceof Name) {
                $className = $value->class->toString();

                return $this->astHelper->resolveClassName($className, $ast, $namespace);
            }
        }

        if ($value instanceof String_) {
            return $this->astHelper->resolveClassName($value->value, $ast, $namespace);
        }

        return null;
    }

    private function formatRelationShortName(string $relationType, string $phpDocReturnType): string
    {
        if (preg_match('/^([A-Za-z0-9_]+)</', $phpDocReturnType, $matches)) {
            return $matches[1];
        }

        return match ($relationType) {
            'belongsTo' => 'BelongsTo',
            'hasOne' => 'HasOne',
            'hasMany' => 'HasMany',
            'belongsToMany' => 'BelongsToMany',
            'morphTo' => 'MorphTo',
            'morphOne' => 'MorphOne',
            'morphMany' => 'MorphMany',
            'morphToMany' => 'MorphToMany',
            'hasManyThrough' => 'HasManyThrough',
            default => ucfirst($relationType),
        };
    }

    /**
     * Extract the type and nullability from a phpstan type.
     *
     * @return array{string, bool}
     */
    private function getTypeFromComment(string $type): array
    {
        $nullable = str_ends_with($type, '|null') ||
            str_ends_with($type, 'null|') ||
            $type === 'null' ||
            str_contains($type, '?');

        $type = str_replace(['|null', 'null|', '?'], '', $type);

        return [$type, $nullable];
    }

    /**
     * Determine array type information from the type string.
     *
     * @return array{string, string|null, string|null} // type, arrayType, keyType
     */
    private function determineArrayInfo(string $type): array
    {
        $arrayType = null;
        $keyType = null;

        // Collection<Type>
        if (str_starts_with($type, 'Collection<') && str_ends_with($type, '>')) {
            $arrayType = 'Collection';
            $inner = substr($type, 11, -1);
            if (str_contains($inner, ',')) {
                $parts = explode(',', $inner, 2);
                $keyType = trim($parts[0]);
                $type = trim($parts[1]);
            } else {
                $type = $inner;
            }
        }
        // array<Type>
        elseif (str_starts_with($type, 'array<') && str_ends_with($type, '>')) {
            $arrayType = 'array';
            $inner = substr($type, 6, -1);
            if (str_contains($inner, ',')) {
                $parts = explode(',', $inner, 2);
                $keyType = trim($parts[0]);
                $type = trim($parts[1]);
            } else {
                $type = $inner;
            }
        }
        // Type[]
        elseif (str_ends_with($type, '[]')) {
            $arrayType = 'array';
            $type = substr($type, 0, -2);
        }

        return [$type, $arrayType, $keyType];
    }

    /**
     * Heuristic to detect if a phpdoc type is likely a class type (needs resolving).
     */
    private function isProbablyClassType(string $type): bool
    {
        $typeLower = strtolower($type);

        $builtIns = [
            'string', 'bool', 'boolean', 'int', 'integer', 'float', 'double', 'mixed', 'object', 'array', 'callable', 'iterable', 'void', 'null', 'resource', 'true', 'false', 'self', 'static', '$this', 'mixed[]', 'string[]', 'int[]', 'bool[]', 'array[]', 'object[]',
        ];

        if (in_array($typeLower, $builtIns, true)) {
            return false;
        }

        // If it has a namespace separator, assume it's a class name
        if (str_contains($type, '\\')) {
            return true;
        }

        // If it starts with an uppercase letter, it's probably a class (e.g., User, Carbon)
        return preg_match('/^[A-Z]/', $type) === 1;
    }
}
