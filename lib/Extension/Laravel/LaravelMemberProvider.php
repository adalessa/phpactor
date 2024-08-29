<?php

namespace Phpactor\Extension\Laravel;

use Phpactor\WorseReflection\Core\Reflection\Collection\ChainReflectionMemberCollection;
use Phpactor\WorseReflection\Core\Reflection\Collection\ReflectionMemberCollection;
use Phpactor\WorseReflection\Core\Reflection\ReflectionClassLike;
use Phpactor\WorseReflection\Core\ServiceLocator;
use Phpactor\WorseReflection\Core\TypeFactory;
use Phpactor\WorseReflection\Core\Virtual\ReflectionMemberProvider;

class LaravelMemberProvider implements ReflectionMemberProvider
{
    private const ELOQUENT_MODEL = '\\Illuminate\\Database\\Eloquent\\Model';
    private const ELOQUENT_BUILDER = '\\Illuminate\\Database\\Eloquent\\Builder';

    public function provideMembers(ServiceLocator $locator, ReflectionClassLike $class): ReflectionMemberCollection
    {
        $members = [];
        if ($class->type()->instanceof(TypeFactory::reflectedClass(
            $locator->reflector(),
            self::ELOQUENT_MODEL,
        ))->isTrue()) {
            $builder = $locator->reflector()->reflectClass(self::ELOQUENT_BUILDER);

            $members[] = $builder->type()->members()->methods();
        }

        return ChainReflectionMemberCollection::fromCollections($members);
    }
}
