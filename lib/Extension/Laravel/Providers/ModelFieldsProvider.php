<?php

namespace Phpactor\Extension\Laravel\Providers;

use Phpactor\Extension\Laravel\ArtisanRunner;
use Phpactor\WorseReflection\Core\ClassName;
use Phpactor\WorseReflection\Core\Reflection\ReflectionEnum;
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
                        'type' => $this->createType($field, $reflector),
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

    private function createType(array $field, Reflector $reflector): Type
    {
        $type = match($field['type']) {
            'varchar', 'text' => TypeFactory::string(),
            'integer' => TypeFactory::int(),
            default => TypeFactory::string(),
        };

        if (str_starts_with($field['type'], 'tinyint')) {
            $type = TypeFactory::int();
        }

        // TODO: suuport more default casts https://laravel.com/docs/11.x/eloquent-mutators#attribute-casting
        $type = match ($field['cast']) {
            'datetime', 'date' => TypeFactory::class('\Illuminate\Support\Carbon'),
            'bool' => TypeFactory::bool(),
            'attribute' => TypeFactory::string(),
            'int' => TypeFactory::int(),
            null => $type,
            default => $this->getTypeFromCast($field['cast'], $reflector),
        };

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

    private function getTypeFromCast(string $cast, Reflector $reflector): Type
    {
        $castClass = ClassName::fromString($cast);
        $reflectionClass = $reflector->reflectClassLike($castClass);

        if (true === $reflectionClass instanceof ReflectionEnum) {
            return $reflectionClass->type();
        }

        // need to get the get and set
        $castTypes = $reflectionClass->docblock()->implements();
        if (count($castTypes) > 0) {
            $castType = $castTypes[0];
            if (true === $castType instanceof GenericClassType) {
                return $castType->arguments()[0];
            }

        }

        return TypeFactory::mixed();
    }
}
