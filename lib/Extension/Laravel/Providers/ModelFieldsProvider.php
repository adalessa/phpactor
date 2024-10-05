<?php

namespace Phpactor\Extension\Laravel\Providers;

use Phpactor\Extension\Laravel\ArtisanRunner;
use Phpactor\WorseReflection\Core\ClassName;
use Phpactor\WorseReflection\Core\Type;
use Phpactor\WorseReflection\Core\Cache;
use Phpactor\WorseReflection\Core\TypeFactory;
use Phpactor\WorseReflection\Core\Type\GenericClassType;
use Phpactor\WorseReflection\Reflector;

class ModelFieldsProvider
{
    public function __construct(
        private readonly ArtisanRunner $artisanRunner,
        private readonly Cache $cache,
    ) {
    }

    public function get(string $model, Reflector $reflector): array
    {
        return $this->cache->getOrSet('laravel-model-'.$model, function() use ($model, $reflector): array {
            $info = $this->artisanRunner->run('model:show', [$model, '--json']);

            if (null == $info) {
                return [];
            }

            return array_merge(
                 array_map(
                    fn ($field) => [
                        'name' => $field['name'],
                        'type' => $this->createType($field),
                    ],
                    $info['attributes'] ?? [],
                ),
                 array_map(
                    fn ($relation) => [
                        'name' => $relation['name'],
                        'type' => $this->createTypeFromRelation($relation, $reflector),
                    ],
                    $info['relations'] ?? [],
                )
            );
        });
    }

    private function createType(array $field): Type
    {
        $type = match($field['type']) {
            'varchar', 'text' => TypeFactory::string(),
            'integer' => TypeFactory::int(),
            default => TypeFactory::string(),
        };

        // TODO: suuport more default casts https://laravel.com/docs/11.x/eloquent-mutators#attribute-casting
        $type = match ($field['cast']) {
            'datetime', 'date' => TypeFactory::class('\Illuminate\Support\Carbon'),
            'bool' => TypeFactory::bool(),
            'attribute' => TypeFactory::string(),
            'int' => TypeFactory::int(),
            default => $type,
        };

        // TODO: need to handle cast custom casts.

        if (isset($field['nullable']) && $field['nullable']) {
            $type = TypeFactory::nullable($type);
        }

        return $type;
    }

    private function createTypeFromRelation(array $relation, Reflector $reflector): Type
    {
        $type = TypeFactory::class($relation['related']);

        return match ($relation['type']) {
            'BelongsToMany', 'HasMany' => new GenericClassType(
                $reflector,
                ClassName::fromString('\Illuminate\Database\Eloquent\Collection'),
                [
                    TypeFactory::int(),
                    $type,
                ],
            ),
            default => $type,
        };
    }
}
