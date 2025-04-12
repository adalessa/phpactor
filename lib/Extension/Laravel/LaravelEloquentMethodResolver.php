<?php

namespace Phpactor\Extension\Laravel;

use Phpactor\WorseReflection\Core\Inference\FunctionArguments;
use Phpactor\WorseReflection\Core\Inference\Resolver\MemberAccess\MemberContextResolver;
use Phpactor\WorseReflection\Core\Reflection\ReflectionMember;
use Phpactor\WorseReflection\Core\Type;
use Phpactor\WorseReflection\Core\TypeFactory;
use Phpactor\WorseReflection\Core\Type\GenericClassType;
use Phpactor\WorseReflection\Reflector;

class LaravelEloquentMethodResolver implements MemberContextResolver
{
    private const ELOQUENT_MODEL = '\\Illuminate\\Database\\Eloquent\\Model';
    private const ELOQUENT_RELATION = '\\Illuminate\\Database\\Eloquent\\Relations\\Relation';
    private const ELOQUENT_BUILDER = '\\Illuminate\\Database\\Eloquent\\Builder';

    public function resolveMemberContext(
        Reflector $reflector,
        ReflectionMember $member,
        Type $type,
        ?FunctionArguments $arguments
    ): ?Type {

        if ($member->memberType() !== ReflectionMember::TYPE_METHOD) {
            return null;
        }

        if ($type->instanceof(TypeFactory::reflectedClass($reflector, self::ELOQUENT_MODEL))->isTrue()) {
            $methodName = $member->name();
            $builder = $reflector->reflectClass(self::ELOQUENT_BUILDER);
            if ($builder->members()->byMemberType(ReflectionMember::TYPE_METHOD)->has($methodName)) {
                $builder_member = $builder->members()->byMemberType(ReflectionMember::TYPE_METHOD)->get($methodName);

                // It says collection but it's the wrapper for generic so it's good
                return TypeFactory::collection(
                    $reflector,
                    $builder->type(),
                    $type
                );
            }
        }

        if ($type->instanceof(TypeFactory::reflectedClass($reflector, self::ELOQUENT_RELATION))->isTrue()) {
            $methodName = $member->name();
            $builder = $reflector->reflectClass(self::ELOQUENT_BUILDER);
            if ($builder->members()->byMemberType(ReflectionMember::TYPE_METHOD)->has($methodName)) {
                $builder_member = $builder->members()->byMemberType(ReflectionMember::TYPE_METHOD)->get($methodName);

                $getQueryMember = $member->class()->members()->byMemberType(ReflectionMember::TYPE_METHOD)->get('getQuery');

                /*dump(*/
                /*    $member->class()->type()->upcastToGeneric()->__toString(),*/
                /*    $member->class()->type()->invokeType()->__toString(),*/
                /*    $member->declaringClass()->type()->iterableValueType()->__toString(),*/
                /*    $type->isAugmented(),*/
                /*);*/

                /*return $getQueryMember->inferredType();*/

                /*return $getQueryMember->type();*/

                /*dump(*/
                /*    // $member->class()->type()->__toString(), // HasMany*/
                /*    // $type->__toString(), // HasMany*/
                /*    // In this case I am looking for Answer which should be the generic*/
                /*    // but is not there*/
                /*    $member->scope()*/
                /*);*/

                /*return TypeFactory::collection(*/
                /*    $reflector,*/
                /*    $builder->type(),*/
                /*    $type,*/
                /*);*/
            }
        }
        return null;
    }
}
