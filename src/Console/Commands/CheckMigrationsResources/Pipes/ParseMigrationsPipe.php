<?php

declare(strict_types=1);

namespace Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\Pipes;

use Closure;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\AstHelper;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\AnalysisResultDto;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\FieldDto;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\FieldTable;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\ResourceReportDto;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;

class ParseMigrationsPipe
{
    private AstHelper $astHelper;

    public function __construct()
    {
        $this->astHelper = new AstHelper;
    }

    public function __invoke(
        AnalysisResultDto $dto,
        \Closure $next,
    ): AnalysisResultDto {
        $finder = $this->astHelper->finder();

        $migrationDir =
            base_path()
            . DIRECTORY_SEPARATOR
            . 'database'
            . DIRECTORY_SEPARATOR
            . 'migrations';
        $migrationFiles = [];
        if (is_dir($migrationDir)) {
            $it = new \DirectoryIterator($migrationDir);
            foreach ($it as $f) {
                if (!($f->isFile() && $f->getExtension() === 'php')) {
                    continue;
                }

                $migrationFiles[] = $f->getPathname();
            }
            sort($migrationFiles);
        }

        $tables = [];
        $columnTypes = [];
        $columnNullable = [];

        foreach ($migrationFiles as $mf) {
            try {
                $mf = (string) $mf;
                $code = file_get_contents($mf);
                if ($code === false) {
                    throw new \RuntimeException(
                        "Failed to read migration file: {$mf}",
                    );
                }
                $ast = $this->astHelper->parseString($code);
                if ($ast === null) {
                    throw new \RuntimeException(
                        "Failed to parse migration file: {$mf}",
                    );
                }
                $this->astHelper->attachParentReferences($ast);

                // find Schema::create and Schema::table static calls
                /** @var array<Node> $schemaCalls */
                $schemaCalls = $finder->find($ast, function (Node $node) {
                    return (
                        $node instanceof Node\Expr\StaticCall
                        && $node->class instanceof Node\Name
                        && $node->class->toString() === 'Schema'
                        && $node->name instanceof Identifier
                        && in_array(
                            $node->name->toString(),
                            ['create', 'table'],
                            true,
                        )
                    );
                });

                foreach ($schemaCalls as $call) {
                    if (!$call instanceof Node\Expr\StaticCall) {
                        continue;
                    }

                    $args = $call->args;
                    if (!array_key_exists(0, $args)) {
                        continue;
                    }

                    $firstArg = $args[0];
                    if (
                        !$firstArg instanceof Node\Arg
                        || !$firstArg->value instanceof Node\Scalar\String_
                    ) {
                        continue;
                    }
                    $tableName = $firstArg->value->value;
                    if (!array_key_exists($tableName, $tables)) {
                        $tables[$tableName] = [];
                        $columnTypes[$tableName] = [];
                        $columnNullable[$tableName] = [];
                    }

                    // second arg is closure containing blueprint calls
                    if (!array_key_exists(1, $args)) {
                        continue;
                    }

                    $secondArg = $args[1];
                    if (
                        !$secondArg instanceof Node\Arg
                        || !$secondArg->value instanceof Node\Expr\Closure
                    ) {
                        continue;
                    }

                    $this->collectColumnsFromClosure(
                        $secondArg->value,
                        $tableName,
                        $tables,
                        $columnTypes,
                        $columnNullable,
                    );
                }
            } catch (\Throwable $e) {
                // Error handling can be done in the command
            }
        }

        // Normalize migrations columns
        foreach ($tables as $k => $v) {
            $tables[$k] = array_values(array_unique($v));
        }

        // Create MigrationTable instances for each table
        $migrationTables = [];
        foreach ($tables as $tableName => $columns) {
            $columnInfos = [];
            foreach ($columns as $columnName) {
                $type = $columnTypes[$tableName][$columnName] ?? 'mixed';
                $nullable = $columnNullable[$tableName][$columnName] ?? false;
                $columnInfos[$columnName] = new FieldDto(
                    $columnName,
                    $type,
                    $nullable,
                );
            }
            $migrationTables[$tableName] = new FieldTable($columnInfos);
        }

        $resources = $dto->resources;
        foreach ($migrationTables as $table => $fields) {
            $resourceReport = $resources[$table] ?? new ResourceReportDto;
            $resourceReport->migrationFields = $fields;
            $resources[$table] = $resourceReport;
        }
        $dto->resources = $resources;

        return $next($dto);
    }

    /**
     * @param  array<string, list<string>>  $tables
     * @param  array<string, array<string, string>>  $columnTypes
     * @param  array<string, array<string, bool>>  $columnNullable
     */
    private function collectColumnsFromClosure(
        Node\Expr\Closure $closure,
        string $tableName,
        array &$tables,
        array &$columnTypes,
        array &$columnNullable,
    ): void {
        foreach ($closure->stmts as $stmt) {
            if (
                !(
                    $stmt instanceof Node\Stmt\Expression
                    && $stmt->expr instanceof MethodCall
                )
            ) {
                continue;
            }

            $this->recordColumnDefinition(
                $stmt->expr,
                $tableName,
                $tables,
                $columnTypes,
                $columnNullable,
            );
        }
    }

    /**
     * @param  array<string, list<string>>  $tables
     * @param  array<string, array<string, string>>  $columnTypes
     * @param  array<string, array<string, bool>>  $columnNullable
     */
    private function recordColumnDefinition(
        MethodCall $methodCall,
        string $tableName,
        array &$tables,
        array &$columnTypes,
        array &$columnNullable,
    ): void {
        if (!$this->isOnTable($methodCall)) {
            return;
        }

        $columnName = $this->getColumnName($methodCall);
        if ($columnName !== null) {
            $tables[$tableName][] = $columnName;
            $columnTypes[$tableName][$columnName] = $this->getColumnType($this->getFirstMethodCall(
                $methodCall,
            ));
            $columnNullable[$tableName][$columnName] =
                $this->isColumnNullable($methodCall);

            return;
        }

        $this->recordImplicitColumnDefinition(
            $methodCall,
            $tableName,
            $tables,
            $columnTypes,
            $columnNullable,
        );
    }

    /**
     * @param  array<string, list<string>>  $tables
     * @param  array<string, array<string, string>>  $columnTypes
     * @param  array<string, array<string, bool>>  $columnNullable
     */
    private function recordImplicitColumnDefinition(
        MethodCall $methodCall,
        string $tableName,
        array &$tables,
        array &$columnTypes,
        array &$columnNullable,
    ): void {
        $methodName = $methodCall->name instanceof Identifier
            ? $methodCall->name->toString()
            : null;

        if ($this->isRememberTokenMethod($methodName)) {
            $tables[$tableName][] = 'remember_token';
            $columnTypes[$tableName]['remember_token'] = 'string';
            $columnNullable[$tableName]['remember_token'] = true;

            return;
        }

        if (in_array($methodName, ['timestamps', 'timestampsTz'], true)) {
            $tables[$tableName][] = 'created_at';
            $columnTypes[$tableName]['created_at'] = 'Carbon';
            $columnNullable[$tableName]['created_at'] = true;
            $tables[$tableName][] = 'updated_at';
            $columnTypes[$tableName]['updated_at'] = 'Carbon';
            $columnNullable[$tableName]['updated_at'] = true;

            return;
        }

        if (!in_array($methodName, ['softDeletes', 'softDeletesTz'], true)) {
            return;
        }

        $tables[$tableName][] = 'deleted_at';
        $columnTypes[$tableName]['deleted_at'] = 'Carbon';
        $columnNullable[$tableName]['deleted_at'] = true;
    }

    private function isRememberTokenMethod(string|null $methodName): bool
    {
        return (
            is_string($methodName) && hash_equals('rememberToken', $methodName)
        );
    }

    private function getColumnType(MethodCall $mc): string
    {
        $methodName = $mc->name instanceof Identifier
            ? $mc->name->toString()
            : null;

        if ($methodName === null) {
            return 'mixed';
        }

        $mappings = config()->array('migration-resource-checker.column_type_mappings', []);
        $type = $mappings[$methodName] ?? null;

        if (!(is_string($type) || $type === null)) {
            throw new \RuntimeException(
                'Column type mapping did not return a string or null as expected.',
            );
        }

        return $type ?? 'mixed';
    }

    /**
     * Check if a column method call includes .nullable() in its chain.
     */
    private function isColumnNullable(MethodCall $mc): bool
    {
        // Walk up the method call chain to see if nullable() is called
        $current = $mc;
        while ($current instanceof MethodCall) {
            if (
                $current->name instanceof Identifier
                && $current->name->toString() === 'nullable'
            ) {
                return true;
            }
            $current = $current->var;
        }

        return false;
    }

    private function isOnTable(MethodCall $mc): bool
    {
        $current = $mc;
        while ($current instanceof MethodCall) {
            if (
                $current->var instanceof Node\Expr\Variable
                && $current->var->name === 'table'
            ) {
                return true;
            }
            $current = $current->var;
        }

        return false;
    }

    private function getColumnName(MethodCall $mc): string|null
    {
        $mappings = config()->array('migration-resource-checker.column_type_mappings', []);
        $columnMethods = array_keys($mappings);

        $current = $mc;
        while ($current instanceof MethodCall) {
            $methodName = $current->name instanceof Identifier
                ? $current->name->toString()
                : null;
            if (in_array($methodName, $columnMethods, true)) {
                if (
                    !empty($current->args)
                    && $current->args[0] instanceof Node\Arg
                    && $current->args[0]->value instanceof Node\Scalar\String_
                ) {
                    return $current->args[0]->value->value;
                }
            }
            $current = $current->var;
        }

        return null;
    }

    private function getFirstMethodCall(MethodCall $mc): MethodCall
    {
        $current = $mc;
        while ($current->var instanceof MethodCall) {
            $current = $current->var;
        }

        return $current;
    }
}
