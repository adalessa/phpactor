<?php

namespace Phpactor\Extension\Laravel;

use Phpactor\Container\Container;
use Phpactor\Container\ContainerBuilder;
use Phpactor\Container\OptionalExtension;
use Phpactor\Extension\CompletionWorse\CompletionWorseExtension;
use Phpactor\Extension\Completion\CompletionExtension;
use Phpactor\Extension\Laravel\Providers\ViewsProvider;
use Phpactor\Extension\ObjectRenderer\ObjectRendererExtension;
use Phpactor\Extension\WorseReflection\WorseReflectionExtension;
use Phpactor\MapResolver\Resolver;
use Phpactor\WorseReflection\Reflector;

class LaravelExtension implements OptionalExtension
{
    const ARTISAN_COMMAND = 'laravel.artisan_command';
    const PARAM_ENABLED = 'laravel.enabled';

    public function name(): string
    {
        return 'laravel';
    }

    public function load(ContainerBuilder $container): void
    {
        // This resolves inside a method not it self

        $container->register(LaravelEloquentMethodResolver::class, function (Container $container) {
            return new LaravelEloquentMethodResolver();
        }, [ WorseReflectionExtension::TAG_MEMBER_TYPE_RESOLVER => []]);

        $container->register(LaravelMemberProvider::class, function(Container $container) {
            return new LaravelMemberProvider();
        }, [ WorseReflectionExtension::TAG_MEMBER_PROVIDER => []]);

        // extend this to handle multiples.
        $container->register(LaravelCompletor::class, function(Container $container) {
            return new LaravelCompletor(
                $container->expect(WorseReflectionExtension::SERVICE_REFLECTOR, Reflector::class),
                $container->get(CompletionExtension::SERVICE_SHORT_DESC_FORMATTER),
                $container->get(CompletionExtension::SERVICE_SNIPPET_FORMATTER),
                $container->get(ObjectRendererExtension::SERVICE_MARKDOWN_RENDERER)
            );
        }, [
                CompletionWorseExtension::TAG_TOLERANT_COMPLETOR => [
                    'name' => 'laravel',
                ]
            ]);

        $container->register(LaravelViewCompletor::class, function(Container $container) {
            return new LaravelViewCompletor(
                new ViewsProvider(),
            );
        }, [
                CompletionWorseExtension::TAG_TOLERANT_COMPLETOR => [
                    'name' => 'laravel-view',
                ]
            ]);
    }

    public function configure(Resolver $schema): void
    {
        $schema->setDefaults([
            'completion_worse.completor.laravel.enabled' => true,
            'completion_worse.completor.laravel-view.enabled' => true,
            self::PARAM_ENABLED => true,
            self::ARTISAN_COMMAND => "php artisan",
        ]);

        $schema->setDescriptions([
            self::ARTISAN_COMMAND => 'command to execute artisan'
        ]);
    }
}
