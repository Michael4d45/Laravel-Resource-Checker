<?php

declare(strict_types=1);

namespace Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Surveyor\Analyzed\ClassResult;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Types\AbstractType as SurveyorAbstractType;
use Laravel\Surveyor\Types\ClassType as SurveyorClassType;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\ModelAnalysisResultDto;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\ModelFieldDto;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\ModelFieldTable;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\RelationshipFieldDto;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\RelationshipFieldTable;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;

class SurveyorModelAnalyzer
{
    public function __construct(
        private readonly Analyzer $analyzer,
    ) {}

    /**
     * @param  array<Node>  $ast
     */
    public function analyze(
        string $className,
        array $ast,
        string $namespace,
    ): ModelAnalysisResultDto {
        $model = new $className;
        if (!$model instanceof Model) {
            return new ModelAnalysisResultDto($this->guessTableName(
                $className,
            ));
        }

        $tableName = $model->getTable();
        $modelFields = $this->buildModelFields($model, $ast);
        $relationships = $this->buildRelationships($className);

        return new ModelAnalysisResultDto(
            tableName: $tableName,
            modelFields: $modelFields,
            modelRelationships: $relationships,
        );
    }

    /**
     * @param  array<Node>  $ast
     */
    private function buildModelFields(Model $model, array $ast): ModelFieldTable
    {
        $fields = [];
        $accessorAttributes = $this->extractAccessorAttributes($ast);

        foreach ($model->getFillable() as $fieldName) {
            $fields[$fieldName] = new ModelFieldDto(
                name: $fieldName,
                fillable: true,
            );
        }

        foreach ($model->getHidden() as $fieldName) {
            if (array_key_exists($fieldName, $fields)) {
                $fields[$fieldName]->hidden = true;
            } else {
                $fields[$fieldName] = new ModelFieldDto(
                    name: $fieldName,
                    hidden: true,
                );
            }
        }

        foreach ($model->getCasts() as $fieldName => $castType) {
            if (array_key_exists($fieldName, $fields)) {
                $fields[$fieldName]->cast = $castType;
            } else {
                $fields[$fieldName] = new ModelFieldDto(
                    name: $fieldName,
                    cast: $castType,
                );
            }
        }

        $analyzed = $this->analyzer->analyzeClass($model::class)->analyzed();
        $classResult = $analyzed?->result();

        if ($classResult instanceof ClassResult) {
            foreach ($classResult->publicProperties() as $property) {
                if (!$property->modelAttribute) {
                    continue;
                }

                if (!array_key_exists($property->name, $fields)) {
                    $fields[$property->name] =
                        new ModelFieldDto(name: $property->name);
                }

                $fields[$property->name]->accessor = in_array(
                    $property->name,
                    $accessorAttributes,
                    true,
                );

                if (
                    $property->type instanceof SurveyorAbstractType
                    && $fields[$property->name]->accessor
                    && $fields[$property->name]->cast === null
                ) {
                    $fields[$property->name]->cast = $this->typeToString($property->type);
                }

                if ($property->type instanceof SurveyorAbstractType) {
                    $fields[$property->name]->nullable =
                        $property->type->isNullable();
                }
            }
        }

        return new ModelFieldTable($fields);
    }

    /**
     * @param  array<Node>  $ast
     * @return array<string>
     */
    private function extractAccessorAttributes(array $ast): array
    {
        $attributes = [];

        $methods = new \PhpParser\NodeFinder()->findInstanceOf(
            $ast,
            ClassMethod::class,
        );

        foreach ($methods as $method) {
            if (!$method->isPublic()) {
                continue;
            }

            $methodName = $method->name->toString();
            if (
                preg_match('/^get(.+)Attribute$/', $methodName, $matches) !== 1
            ) {
                continue;
            }

            $attributes[] = Str::snake($matches[1]);
        }

        return array_values(array_unique($attributes));
    }

    private function buildRelationships(string $className): RelationshipFieldTable
    {
        $relationships = [];
        $analyzed = $this->analyzer->analyzeClass($className)->analyzed();
        $classResult = $analyzed?->result();

        if ($classResult instanceof ClassResult) {
            foreach ($classResult->publicMethods() as $method) {
                if (!$method->isModelRelation()) {
                    continue;
                }

                $relationType = 'Relation';
                $relatedModel = 'mixed';
                $returnType = $method->returnType();

                if ($returnType instanceof SurveyorClassType) {
                    $relationType = class_basename($returnType->resolved());
                    $genericTypes = $returnType->genericTypes();
                    $relatedType = $genericTypes['TRelatedModel'] ?? null;
                    if ($relatedType instanceof SurveyorClassType) {
                        $relatedModel = $relatedType->resolved();
                    }
                }

                $relationships[$method->name()] = new RelationshipFieldDto(
                    $method->name(),
                    $relationType,
                    $relatedModel,
                );
            }
        }

        return new RelationshipFieldTable($relationships);
    }

    private function typeToString(SurveyorAbstractType $type): string
    {
        if ($type instanceof SurveyorClassType) {
            return $type->resolved();
        }

        $normalized = preg_replace('/^[^:]+:/', '', $type->toString());

        return is_string($normalized) && $normalized !== ''
            ? $normalized
            : 'mixed';
    }

    private function guessTableName(string $className): string
    {
        return strtolower(class_basename($className)) . 's';
    }
}
