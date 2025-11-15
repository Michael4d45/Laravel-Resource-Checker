<?php

declare(strict_types=1);

namespace Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\Pipes;

use Illuminate\Console\Command;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\AnalysisResultDto;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\FieldDto;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\FieldTable;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

class FixAddFieldsToResourcesPipe
{
    private const READONLY_FIELDS = [
        'created_at',
        'updated_at',
        'deleted_at',
        'id',
    ];

    public function __construct(private Command $command) {}

    public function __invoke(AnalysisResultDto $dto, \Closure $next): AnalysisResultDto
    {
        if (empty($dto->report)) {
            return $next($dto);
        }
        /** @var array<string, FieldTable> $addFields */
        $addFields = $dto->report->addFieldsToFilamentForm;
        $resourceFilePaths = array_flip($dto->filamentResourceModelMap);

        $tablesToProcess = array_values(array_unique(array_merge(array_keys($addFields), array_keys($resourceFilePaths))));

        foreach ($tablesToProcess as $table) {
            if (! isset($resourceFilePaths[$table])) {
                $this->command->warn("Resource file for table {$table} not found.");

                continue;
            }

            $resourceFilePath = $resourceFilePaths[$table];
            $formFilePath = dirname($resourceFilePath) . DIRECTORY_SEPARATOR . 'Schemas' . DIRECTORY_SEPARATOR . basename($resourceFilePath, 'Resource.php') . 'Form.php';

            if (! file_exists($formFilePath)) {
                $this->command->warn("Form schema file not found for resource {$table} at {$formFilePath}");

                continue;
            }

            try {
                $code = file_get_contents($formFilePath);
                if ($code === false) {
                    $this->command->error("Failed to read {$formFilePath}");

                    continue;
                }

                $parser = (new ParserFactory)->createForHostVersion();
                $ast = $parser->parse($code);
                if ($ast === null) {
                    $this->command->error("Failed to parse {$formFilePath}");

                    continue;
                }

                $nodeFinder = new NodeFinder;
                $returnStmt = $nodeFinder->findFirst($ast, function ($node) {
                    return $node instanceof Return_;
                });
                if (! $returnStmt instanceof Return_) {
                    $this->command->error("Could not find return statement in {$formFilePath}");

                    continue;
                }

                $methodCall = $returnStmt->expr;
                if (! $methodCall instanceof MethodCall) {
                    $this->command->error("Could not find components method call in {$formFilePath}");

                    continue;
                }

                $methodName = $methodCall->name;
                if (! $methodName instanceof Identifier || $methodName->name !== 'components') {
                    $this->command->error("Could not find components method call in {$formFilePath}");

                    continue;
                }

                $firstArg = $methodCall->args[0] ?? null;
                if (! $firstArg instanceof Arg) {
                    $this->command->error("Components arg is not provided in {$formFilePath}");

                    continue;
                }

                $arrayArg = $firstArg->value;
                if (! $arrayArg instanceof Array_) {
                    $this->command->error("Components arg is not an array in {$formFilePath}");

                    continue;
                }

                $existingFields = $this->extractExistingFieldNames($arrayArg);

                // map of field name => component definition to avoid duplicates from different sources
                $newFields = [];
                $newFieldByName = [];
                $fieldTableDto = $addFields[$table] ?? new FieldTable;
                foreach ($fieldTableDto as $fieldDto) {
                    $field = $fieldDto->name;
                    if (in_array($field, $existingFields, true) || isset($newFieldByName[$field])) {
                        continue;
                    }
                    $dbType = $fieldDto->type;
                    $isNullable = $fieldDto->nullable;
                    $definition = $this->createComponentDefinition($field, $dbType, $isNullable, $code);
                    $newFieldByName[$field] = $definition;
                }

                $readonlyFields = $this->collectReadonlyFields(
                    $dto->resources[$table]->migrationFields ?? new FieldTable,
                    $existingFields,
                );
                foreach ($readonlyFields as $fieldDto) {
                    $fieldName = $fieldDto->name;
                    if (in_array($fieldName, $existingFields, true) || isset($newFieldByName[$fieldName])) {
                        continue;
                    }
                    $newFieldByName[$fieldName] = $this->createComponentDefinition($fieldDto->name, $fieldDto->type, $fieldDto->nullable, $code);
                }

                if (empty($newFieldByName)) {
                    continue;
                }

                $code = $this->insertNewComponentFields($code, $arrayArg, array_values($newFieldByName));

                file_put_contents($formFilePath, $code);
                $this->command->info('Added ' . count($newFields) . " missing fields to {$formFilePath}");

            } catch (\Throwable $e) {
                $this->command->error("Failed to fix {$formFilePath}: " . $e->getMessage());
            }
        }

        return $next($dto);
    }

    /**
     * Insert the generated component entries as text into the existing components array without
     * rewriting the entire file so the existing formatting stays unchanged.
     *
     * @param  list<string>  $newFields
     */
    private function insertNewComponentFields(string $code, Array_ $array, array $newFields): string
    {
        $arrayStart = $array->getAttribute('startFilePos');
        $arrayEnd = $array->getAttribute('endFilePos');

        if (! is_int($arrayStart) || ! is_int($arrayEnd)) {
            return $code;
        }

        $arraySegment = substr($code, $arrayStart, $arrayEnd - $arrayStart + 1);
        $indent = '            ';
        if (preg_match('/\[\s*\n([ \t]+)/', $arraySegment, $matches)) {
            $indent = $matches[1];
        }

        $items = [];
        foreach ($newFields as $field) {
            $items[] = $indent . $field . ',';
        }

        $insertText = '';
        foreach ($items as $item) {
            $insertText .= "\n{$item}";
        }
        $insertText .= "\n";

        $insertPosition = $arrayEnd;
        $beforeArrayEnd = substr($code, 0, $arrayEnd);
        if (preg_match('/(?:\n[ \t]*\/\/[^\n]*)+\s*$/', $beforeArrayEnd, $matches, PREG_OFFSET_CAPTURE)) {
            $insertPosition = $matches[0][1];
        } else {
            $newlinePosition = strrpos($beforeArrayEnd, "\n");
            if ($newlinePosition !== false) {
                $insertPosition = $newlinePosition;
            }
        }

        return substr($code, 0, $insertPosition) . $insertText . substr($code, $insertPosition);
    }

    private function createComponentDefinition(string $field, string $dbType, bool $isNullable, string $code): string
    {
        $normalizedType = strtolower($dbType);
        $componentFqn = (string) config()->string('check-migrations-resources.resource_component_default', '\\Filament\\Forms\\Components\\TextInput');
        $componentMap = config()->array('check-migrations-resources.resource_component_mappings', []);
        if (isset($componentMap[$normalizedType]) && is_string($componentMap[$normalizedType])) {
            $componentFqn = (string) $componentMap[$normalizedType];
        }

        $componentFqn = '\\' . ltrim($componentFqn, '\\');

        $numericTypes = config()->array('check-migrations-resources.resource_numeric_types', ['int', 'integer']);
        $isNumeric = in_array($normalizedType, $numericTypes, true);

        $componentReference = $this->resolveComponentReference($componentFqn, $code);
        $definition = "{$componentReference}::make('{$field}')";

        if ($isNumeric) {
            $definition .= '->numeric()';
        }

        if (! $isNullable) {
            $definition .= '->required()';
        }

        if (in_array($field, self::READONLY_FIELDS, true)) {
            $definition .= '->disabled()';
        }

        return $definition;
    }

    private function resolveComponentReference(string $componentFqn, string $code): string
    {
        $trimmed = ltrim($componentFqn, '\\');
        $shortName = (str_contains($trimmed, '\\')) ? substr($trimmed, strrpos($trimmed, '\\') + 1) : $trimmed;

        if (str_contains($code, "use {$trimmed};")) {
            return $shortName;
        }

        return '\\' . $trimmed;
    }

    /**
     * @return list<string>
     */
    private function extractExistingFieldNames(Array_ $array): array
    {
        $fields = [];

        /** @var list<ArrayItem|null> $items */
        $items = $array->items;

        foreach ($items as $item) {
            if ($item === null) {
                continue;
            }

            $fieldName = $this->extractFieldNameFromComponentExpr($item->value);
            if ($fieldName !== null) {
                $fields[] = $fieldName;
            }
        }

        return array_values(array_unique($fields));
    }

    private function extractFieldNameFromComponentExpr(Node $expr): string|null
    {
        while ($expr instanceof MethodCall) {
            $expr = $expr->var;
        }

        if (! $expr instanceof StaticCall) {
            return null;
        }

        if (! $expr->name instanceof Identifier || $expr->name->name !== 'make') {
            return null;
        }

        $firstArg = $expr->args[0] ?? null;
        if (! $firstArg instanceof Arg) {
            return null;
        }

        if (! $firstArg->value instanceof String_) {
            return null;
        }

        return $firstArg->value->value;
    }

    /**
     * @param  array<string>  $existingFields
     * @return list<FieldDto>
     */
    private function collectReadonlyFields(FieldTable $migrationFields, array $existingFields): array
    {
        $fields = [];

        foreach (self::READONLY_FIELDS as $field) {
            if (! $migrationFields->has($field)) {
                continue;
            }

            if (in_array($field, $existingFields, true)) {
                continue;
            }

            $fieldDto = $migrationFields->get($field);
            if ($fieldDto instanceof FieldDto) {
                $fields[] = $fieldDto;
            }
        }

        return $fields;
    }
}
