<?php

declare(strict_types=1);

namespace Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\Pipes;

use Illuminate\Database\Eloquent\Model;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\AstHelper;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\AnalysisResultDto;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\FieldDto;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\FieldTable;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\ResourceReportDto;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;

class ParseFilamentFormsPipe
{
    private AstHelper $astHelper;

    public function __construct()
    {
        $this->astHelper = new AstHelper;
    }

    public function __invoke(AnalysisResultDto $dto, \Closure $next): AnalysisResultDto
    {
        $finder = $this->astHelper->finder();

        $resourcesDir = base_path() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Filament' . DIRECTORY_SEPARATOR . 'Resources';
        $resourceFiles = [];
        if (is_dir($resourcesDir)) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($resourcesDir));
            foreach ($it as $f) {
                if ($f instanceof \SplFileInfo && $f->isFile() && $f->getExtension() === 'php') {
                    $resourceFiles[] = $f->getPathname();
                }
            }
        }
        $formComponentClassNames = $this->normalizeComponentReferences(
            config()->array('migration-resource-checker.form_component_classes', [])
        );

        $resourceFields = [];
        $resourceModelMap = [];

        foreach ($resourceFiles as $rf) {
            $rf = (string) $rf;
            if (strpos($rf, 'RelationManagers') !== false) {
                continue;
            }

            if (substr(basename($rf), -12) !== 'Resource.php') {
                continue;
            }

            $ast = $this->astHelper->parseFile($rf);
            if ($ast === null) {
                continue;
            }
            $this->astHelper->attachParentReferences($ast);

            $namespace = $this->astHelper->getNamespace($ast);

            /** @var array<Node\Stmt\Property> $props */
            $props = $finder->findInstanceOf($ast, Node\Stmt\Property::class);
            foreach ($props as $p) {
                foreach ($p->props as $pp) {
                    if ($pp->name->toString() !== 'model') {
                        continue;
                    }

                    $modelClassName = $this->resolveModelClassName($pp->default ?? null, $ast, $namespace);
                    if ($modelClassName === null) {
                        continue;
                    }

                    $model = $this->instantiateModel($modelClassName);
                    if ($model instanceof Model) {
                        $resourceModelMap[$rf] = $model->getTable();
                    }
                }
            }
        }

        foreach ($resourceFiles as $rf) {
            $rf = (string) $rf;
            if (strpos($rf, 'RelationManagers') !== false) {
                continue;
            }

            if (substr(basename($rf), -12) !== 'Resource.php') {
                continue;
            }

            $tableKey = $resourceModelMap[$rf] ?? null;
            if ($tableKey === null) {
                continue;
            }

            if (! isset($resourceFields[$tableKey])) {
                $resourceFields[$tableKey] = new FieldTable;
            }

            $schemaDir = dirname($rf) . DIRECTORY_SEPARATOR . 'Schemas';
            $formFiles = $this->gatherSchemaFiles($schemaDir, 'Form.php');
            foreach ($formFiles as $formFile) {
                try {
                    $code = file_get_contents($formFile);
                    if ($code === false) {
                        continue;
                    }
                    $ast = $this->astHelper->parseString($code);
                    if ($ast === null) {
                        continue;
                    }
                    $this->astHelper->attachParentReferences($ast);

                    /** @var array<Node> $staticMakes */
                    $staticMakes = $finder->find($ast, function (Node $n) use ($formComponentClassNames) {
                        return $n instanceof StaticCall
                            && $n->class instanceof Name
                            && in_array(ltrim($n->class->toString(), '\\'), $formComponentClassNames, true)
                            && $n->name instanceof Identifier
                            && $n->name->toString() === 'make'
                            && isset($n->args[0])
                            && $n->args[0] instanceof Arg
                            && $n->args[0]->value instanceof String_;
                    });

                    foreach ($staticMakes as $sm) {
                        if (! $sm instanceof StaticCall || ! isset($sm->args[0])) {
                            continue;
                        }
                        $firstArg = $sm->args[0];
                        if (! $firstArg instanceof Arg || ! $firstArg->value instanceof String_) {
                            continue;
                        }
                        $field = (string) $firstArg->value->value;
                        $type = $this->inferFieldTypeFromComponent($sm->class);
                        $nullable = ! $this->hasRequiredModifier($sm);
                        $this->storeField($resourceFields[$tableKey], $field, $type, $nullable);
                    }
                } catch (\Throwable $e) {
                    // ignore parse errors
                }
            }

        }

        $dto->filamentResourceModelMap = $resourceModelMap;

        $resources = $dto->resources;
        foreach ($resourceFields as $table => $fields) {
            $resourceReport = $resources[$table] ?? new ResourceReportDto;
            $resourceReport->filamentFormFields = $fields;
            $resources[$table] = $resourceReport;
        }
        $dto->resources = $resources;

        return $next($dto);
    }

    /**
     * @param  array<int, mixed>  $configClasses
     * @return array<int, string>
     */
    private function normalizeComponentReferences(array $configClasses): array
    {
        $normalized = [];

        foreach ($configClasses as $class) {
            if (! is_string($class)) {
                continue;
            }

            $trimmed = ltrim($class, '\\');
            $normalized[] = $trimmed;

            $normalized[] = basename(str_replace('\\', '/', $trimmed));
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param  array<Node>  $ast
     */
    private function resolveModelClassName(Node\Expr|null $default, array $ast, string $namespace): string|null
    {
        if ($default instanceof ClassConstFetch && $default->name instanceof Identifier && $default->name->toString() === 'class') {
            $classNode = $default->class;
            if ($classNode instanceof Name) {
                return $this->astHelper->resolveClassName($classNode->toString(), $ast, $namespace);
            }

            return null;
        }

        if ($default instanceof String_) {
            return ltrim($default->value, '\\');
        }

        return null;
    }

    private function instantiateModel(string $className): Model|null
    {
        $className = ltrim($className, '\\');

        if ($className === '' || ! class_exists($className) || ! is_subclass_of($className, Model::class)) {
            return null;
        }

        return new $className;
    }

    /**
     * @return array<int, string>
     */
    private function gatherSchemaFiles(string $directory, string $suffix): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if (! $file instanceof \SplFileInfo || ! $file->isFile()) {
                continue;
            }

            if (str_ends_with($file->getFilename(), $suffix)) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function inferFieldTypeFromComponent(Node $componentClass): string
    {
        $resolved = $this->resolveComponentClassName($componentClass);
        if ($resolved === null) {
            return 'string';
        }
        $typeMap = config()->array('migration-resource-checker.resource_component_type_map', []);

        $type = $typeMap[$resolved] ?? null;
        if (! is_string($type)) {
            $shortName = basename(str_replace('\\', '/', $resolved));
            $type = $typeMap[$shortName] ?? null;
        }

        if (is_string($type)) {
            return $type;
        }

        return 'string';
    }

    private function resolveComponentClassName(Node $componentClass): string|null
    {
        if ($componentClass instanceof Name) {
            return ltrim($componentClass->toString(), '\\');
        }

        return null;
    }

    private function hasRequiredModifier(StaticCall $componentCall): bool
    {
        $requiredMethods = config()->array('migration-resource-checker.resource_component_required_methods', []);
        $current = $componentCall->getAttribute('parent');

        while ($current instanceof MethodCall) {
            if ($current->name instanceof Identifier && in_array($current->name->toString(), $requiredMethods, true)) {
                return true;
            }

            $current = $current->getAttribute('parent');
        }

        return false;
    }

    private function storeField(FieldTable $table, string $field, string $type, bool $nullable): void
    {
        $table->put($field, new FieldDto($field, $type, $nullable));
    }
}
