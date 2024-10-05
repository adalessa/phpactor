<?php

namespace Phpactor\Extension\Laravel;

use Phpactor\Extension\Laravel\Providers\ModelFieldsProvider;
use Phpactor\WorseReflection\Core\Deprecation;
use Phpactor\WorseReflection\Core\Inference\Frame;
use Phpactor\WorseReflection\Core\Reflection\Collection\ChainReflectionMemberCollection;
use Phpactor\WorseReflection\Core\Reflection\Collection\HomogeneousReflectionMemberCollection;
use Phpactor\WorseReflection\Core\Reflection\Collection\ReflectionMemberCollection;
use Phpactor\WorseReflection\Core\Reflection\Collection\ReflectionMethodCollection;
use Phpactor\WorseReflection\Core\Reflection\ReflectionClassLike;
use Phpactor\WorseReflection\Core\ServiceLocator;
use Phpactor\WorseReflection\Core\TypeFactory;
use Phpactor\WorseReflection\Core\Virtual\ReflectionMemberProvider;
use Phpactor\WorseReflection\Core\Virtual\VirtualReflectionProperty;
use Phpactor\WorseReflection\Core\Visibility;

class LaravelMemberProvider implements ReflectionMemberProvider
{
    private const ELOQUENT_MODEL = '\\Illuminate\\Database\\Eloquent\\Model';
    private const ELOQUENT_BUILDER = '\\Illuminate\\Database\\Eloquent\\Builder';

    public function __construct(
        private readonly ModelFieldsProvider $modelFieldsProvider,
    ) {
    }

    public function provideMembers(ServiceLocator $locator, ReflectionClassLike $class): ReflectionMemberCollection
    {
        $members = [];
        if ($class->type()->instanceof(TypeFactory::reflectedClass(
            $locator->reflector(),
            self::ELOQUENT_MODEL,
        ))->isTrue()) {
            $builder = $locator->reflector()->reflectClass(self::ELOQUENT_BUILDER);

            // TODO: need to get the methods and change the return type of the member
            // like findOrFail() should
            // could map, check the return type if its TModel replace with the defined type
            $members[] = $this->retypeMethods($builder->type()->members()->methods());

            // here could add the virtual methods for the specifc class
            if ($class->type()->name()->head() != "Illuminate") {
                $properties = [];
                    foreach ($this->modelFieldsProvider->get($class->type()->name()->full(), $locator->reflector()) as $field) {
                        $properties[] = new VirtualReflectionProperty(
                            $class->position(),
                            $class,
                            $class,
                            $field['name'],
                            new Frame(),
                            $class->docblock(),
                            $class->scope(),
                            Visibility::public(),
                            $field['type'],
                            $field['type'],
                            new Deprecation(false),
                        );
                    }

                    $memberCollection = HomogeneousReflectionMemberCollection::fromMembers($properties);
                    $members[] = $memberCollection;
            }
        }

        return ChainReflectionMemberCollection::fromCollections($members);
    }

    private function retypeMethods(ReflectionMethodCollection $methods): ReflectionMethodCollection
    {
        return $methods;
    }
}
