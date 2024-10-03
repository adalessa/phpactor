<?php

namespace Phpactor\Extension\Laravel;

use Phpactor\Container\Container;
use Phpactor\Container\ContainerBuilder;
use Phpactor\Container\OptionalExtension;
use Phpactor\Extension\CompletionWorse\CompletionWorseExtension;
use Phpactor\Extension\Completion\CompletionExtension;
use Phpactor\Extension\Laravel\Providers\ConfigsProvider;
use Phpactor\Extension\Laravel\Providers\ModelFieldsProvider;
use Phpactor\Extension\Laravel\Providers\RoutesProvider;
use Phpactor\Extension\Laravel\Providers\ViewsProvider;
use Phpactor\Extension\Logger\LoggingExtension;
use Phpactor\Extension\ObjectRenderer\ObjectRendererExtension;
use Phpactor\Extension\WorseReflection\WorseReflectionExtension;
use Phpactor\MapResolver\Resolver;
use Phpactor\WorseReflection\Reflector;
use Psr\Log\LoggerInterface;

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
            return new LaravelMemberProvider(
                new ModelFieldsProvider(
                    $container->get(ArtisanRunner::class),
                ),
            );
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
                'name' => 'laravel-views',
            ]
        ]);

        $container->register(LaravelConfigCompletor::class, function(Container $container) {
            return new LaravelConfigCompletor(
                new ConfigsProvider(
                    $container->get(ArtisanRunner::class),
                ),
            );
        }, [
            CompletionWorseExtension::TAG_TOLERANT_COMPLETOR => [
                'name' => 'laravel-configs',
            ]
        ]);

        $container->register(LaravelRoutesCompletor::class, function(Container $container) {
            return new LaravelRoutesCompletor(
                new RoutesProvider(
                    $container->get(ArtisanRunner::class),
                ),
            );
        }, [
            CompletionWorseExtension::TAG_TOLERANT_COMPLETOR => [
                'name' => 'laravel-routes',
            ]
        ]);

        $container->register(ArtisanRunner::class, function(Container $container) {
            return new ArtisanRunner(
                $container->parameter(self::ARTISAN_COMMAND)->string(),
                LoggingExtension::channelLogger($container, 'laravel'),
            );
        });
    }

    public function configure(Resolver $schema): void
    {
        $schema->setDefaults([
            'completion_worse.completor.laravel.enabled' => true,
            'completion_worse.completor.laravel-views.enabled' => true,
            'completion_worse.completor.laravel-configs.enabled' => true,
            'completion_worse.completor.laravel-routes.enabled' => true,
            self::PARAM_ENABLED => true,
            self::ARTISAN_COMMAND => "php artisan",
        ]);

        $schema->setDescriptions([
            self::ARTISAN_COMMAND => 'command to execute artisan'
        ]);
    }
}
