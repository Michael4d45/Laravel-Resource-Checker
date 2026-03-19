<?php

declare(strict_types=1);

namespace Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\Services;

use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\AstHelper;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\PhpDocFieldDto;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\DTOs\PhpDocFieldTable;
use phpDocumentor\Reflection\DocBlock\Tags\Property;
use phpDocumentor\Reflection\DocBlock\Tags\PropertyRead;
use phpDocumentor\Reflection\DocBlock\Tags\PropertyWrite;
use phpDocumentor\Reflection\DocBlock\Tags\TagWithType;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\DocBlockFactoryInterface;
use PhpParser\Node;

class DocBlockTypeExtractor
{
    private DocBlockFactoryInterface $docBlockFactory;

    public function __construct(
        private readonly AstHelper $astHelper = new AstHelper,
    ) {
        $this->docBlockFactory = DocBlockFactory::createInstance();
    }

    /**
     * @param  array<Node>  $ast
     * @return array{properties: PhpDocFieldTable, read: PhpDocFieldTable}
     */
    public function extract(
        string $docComment,
        array $ast,
        string $namespace,
    ): array {
        $docBlock = $this->docBlockFactory->create($docComment);

        $properties = [];
        $read = [];

        foreach (['property', 'property-write'] as $tagName) {
            foreach ($docBlock->getTagsWithTypeByName($tagName) as $tag) {
                $fieldDto = $this->toFieldDto($tag, $ast, $namespace);
                if ($fieldDto === null) {
                    continue;
                }

                $properties[$fieldDto->name] = $fieldDto;
            }
        }

        foreach ($docBlock->getTagsWithTypeByName('property-read') as $tag) {
            $fieldDto = $this->toFieldDto($tag, $ast, $namespace);
            if ($fieldDto === null) {
                continue;
            }

            $read[$fieldDto->name] = $fieldDto;
        }

        return [
            'properties' => new PhpDocFieldTable($properties),
            'read' => new PhpDocFieldTable($read),
        ];
    }

    /**
     * @param  array<Node>  $ast
     */
    private function toFieldDto(
        TagWithType $tag,
        array $ast,
        string $namespace,
    ): null|PhpDocFieldDto {
        if (
            !(
                $tag instanceof Property
                || $tag instanceof PropertyRead
                || $tag instanceof PropertyWrite
            )
        ) {
            return null;
        }

        $fieldName = $tag->getVariableName();

        if (!is_string($fieldName) || $fieldName === '') {
            return null;
        }

        $type = (string) $tag->getType();
        [$type, $nullable] = $this->getTypeFromComment($type);
        [$type, $arrayType, $keyType] = $this->determineArrayInfo($type);

        if ($this->isProbablyClassType($type)) {
            $type = $this->astHelper->resolveClassName($type, $ast, $namespace);
        }

        return new PhpDocFieldDto(
            $fieldName,
            $type,
            $nullable,
            $arrayType,
            $keyType,
        );
    }

    /**
     * @return array{string, bool}
     */
    private function getTypeFromComment(string $type): array
    {
        $nullable =
            str_ends_with($type, '|null')
            || str_ends_with($type, 'null|')
            || $type === 'null'
            || str_contains($type, '?');

        $type = str_replace(['|null', 'null|', '?'], '', $type);

        return [$type, $nullable];
    }

    /**
     * @return array{string, string|null, string|null}
     */
    private function determineArrayInfo(string $type): array
    {
        $arrayType = null;
        $keyType = null;

        if (
            str_starts_with($type, 'Collection<') && str_ends_with($type, '>')
        ) {
            $arrayType = 'Collection';
            $inner = substr($type, 11, -1);
            if (str_contains($inner, ',')) {
                $parts = explode(',', $inner, 2);
                $keyType = trim($parts[0]);
                $type = trim($parts[1]);
            } else {
                $type = $inner;
            }
        } elseif (
            str_starts_with($type, 'array<') && str_ends_with($type, '>')
        ) {
            $arrayType = 'array';
            $inner = substr($type, 6, -1);
            if (str_contains($inner, ',')) {
                $parts = explode(',', $inner, 2);
                $keyType = trim($parts[0]);
                $type = trim($parts[1]);
            } else {
                $type = $inner;
            }
        } elseif (str_ends_with($type, '[]')) {
            $arrayType = 'array';
            $type = substr($type, 0, -2);
        }

        return [$type, $arrayType, $keyType];
    }

    private function isProbablyClassType(string $type): bool
    {
        $typeLower = strtolower($type);

        $builtIns = [
            'string',
            'bool',
            'boolean',
            'int',
            'integer',
            'float',
            'double',
            'mixed',
            'object',
            'array',
            'callable',
            'iterable',
            'void',
            'null',
            'resource',
            'true',
            'false',
            'self',
            'static',
            '$this',
            'mixed[]',
            'string[]',
            'int[]',
            'bool[]',
            'array[]',
            'object[]',
        ];

        if (in_array($typeLower, $builtIns, true)) {
            return false;
        }

        if (str_contains($type, '\\')) {
            return true;
        }

        return preg_match('/^[A-Z]/', $type) === 1;
    }
}
