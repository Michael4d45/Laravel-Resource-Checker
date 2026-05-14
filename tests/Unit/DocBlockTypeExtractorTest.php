<?php

declare(strict_types=1);

use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\AstHelper;
use Michael4d45\LaravelResourceChecker\Console\Commands\CheckMigrationsResources\Services\DocBlockTypeExtractor;

test('docblock type extractor parses property tags', function (): void {
    $extractor = new DocBlockTypeExtractor(new AstHelper);

    $code = <<<'PHP'
<?php

namespace App\Models;

/**
 * @property int $id
 * @property string|null $name
 * @property-read Collection<int, string> $labels
 */
class Post extends \Illuminate\Database\Eloquent\Model
{
}
PHP;

    $docComment = <<<'DOC'
/**
 * @property int $id
 * @property string|null $name
 * @property-read array<int, string> $labels
 */
DOC;

    $ast = (new AstHelper)->parseString($code);
    expect($ast)->not->toBeNull();

    $result = $extractor->extract($docComment, $ast ?? [], 'App\\Models');

    expect($result['properties'])->toHaveCount(2);
    expect($result['properties']->get('id')?->type)->toBe('int');
    expect($result['properties']->get('name')?->type)->toBe('string');
    expect($result['properties']->get('name')?->nullable)->toBeTrue();

    expect($result['read'])->toHaveCount(1);
    expect($result['read']->get('labels')?->arrayType)->toBe('array');
    expect($result['read']->get('labels')?->keyType)->toBe('int');
    expect($result['read']->get('labels')?->type)->toBe('string');
});

test('docblock type extractor parses list generic as array-shaped', function (): void {
    $extractor = new DocBlockTypeExtractor(new AstHelper);

    $code = <<<'PHP'
<?php

namespace App\Models;

/**
 * @property list<string>|null $tags
 */
class Playlist extends \Illuminate\Database\Eloquent\Model
{
}
PHP;

    $docComment = <<<'DOC'
/**
 * @property list<string>|null $tags
 */
DOC;

    $ast = (new AstHelper)->parseString($code);
    expect($ast)->not->toBeNull();

    $result = $extractor->extract($docComment, $ast ?? [], 'App\\Models');

    expect($result['properties']->get('tags')?->arrayType)->toBe('array');
    expect($result['properties']->get('tags')?->type)->toBe('string');
    expect($result['properties']->get('tags')?->nullable)->toBeTrue();
});