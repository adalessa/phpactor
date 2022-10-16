<?php

namespace Phpactor;

use Phpactor\ClassMover\Extension\ClassMoverExtension as MainClassMoverExtension;
use Phpactor\Container\Container;
use Phpactor\Extension\Behat\BehatExtension;
use Phpactor\Extension\Debug\DebugExtension;
use Phpactor\Extension\LanguageServerBlackfire\LanguageServerBlackfireExtension;
use Phpactor\Extension\LanguageServerPhpCsFixer\LanguageServerPhpCsFixerExtension;
use Phpactor\Extension\LanguageServerPhpstan\LanguageServerPhpstanExtension;
use Phpactor\Extension\LanguageServerBridge\LanguageServerBridgeExtension;
use Phpactor\Extension\LanguageServerCodeTransform\LanguageServerCodeTransformExtension;
use Phpactor\Extension\LanguageServerCompletion\LanguageServerCompletionExtension;
use Phpactor\Extension\LanguageServerDiagnostics\LanguageServerDiagnosticsExtension;
use Phpactor\Extension\LanguageServerHover\LanguageServerHoverExtension;
use Phpactor\Extension\LanguageServerIndexer\LanguageServerIndexerExtension;
use Phpactor\Extension\LanguageServerPsalm\LanguageServerPsalmExtension;
use Phpactor\Extension\LanguageServerReferenceFinder\LanguageServerReferenceFinderExtension;
use Phpactor\Extension\LanguageServerRename\LanguageServerRenameExtension;
use Phpactor\Extension\LanguageServerRename\LanguageServerRenameWorseExtension;
use Phpactor\Extension\LanguageServerSymbolProvider\LanguageServerSymbolProviderExtension;
use Phpactor\Extension\LanguageServerSelectionRange\LanguageServerSelectionRangeExtension;
use Phpactor\Extension\LanguageServerWorseReflection\LanguageServerWorseReflectionExtension;
use Phpactor\Extension\LanguageServer\LanguageServerExtension;
use Phpactor\Extension\LanguageServer\LanguageServerExtraExtension;
use Phpactor\Extension\ObjectRenderer\ObjectRendererExtension;
use Phpactor\Extension\Symfony\SymfonyExtension;
use Phpactor\Extension\WorseReflectionAnalyse\WorseReflectionAnalyseExtension;
use Phpactor\Indexer\Extension\IndexerExtension;
use RuntimeException;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Phpactor\Container\PhpactorContainer;
use Phpactor\Extension\Core\CoreExtension;
use Phpactor\Extension\CodeTransformExtra\CodeTransformExtraExtension;
use Phpactor\Extension\CodeTransform\CodeTransformExtension;
use Phpactor\Extension\CompletionExtra\CompletionExtraExtension;
use Phpactor\Extension\Completion\CompletionExtension;
use Phpactor\Extension\CompletionRpc\CompletionRpcExtension;
use Phpactor\Extension\CompletionWorse\CompletionWorseExtension;
use Phpactor\Extension\Navigation\NavigationExtension;
use Phpactor\Extension\SourceCodeFilesystemExtra\SourceCodeFilesystemExtraExtension;
use Phpactor\Extension\SourceCodeFilesystem\SourceCodeFilesystemExtension;
use Phpactor\Extension\WorseReflectionExtra\WorseReflectionExtraExtension;
use Phpactor\Extension\WorseReferenceFinder\WorseReferenceFinderExtension;
use Phpactor\Extension\WorseReflection\WorseReflectionExtension;
use Phpactor\Extension\ClassMover\ClassMoverExtension;
use Phpactor\Extension\FilePathResolver\FilePathResolverExtension;
use Phpactor\Extension\ContextMenu\ContextMenuExtension;
use Phpactor\Extension\Rpc\RpcExtension;
use Phpactor\Extension\Console\ConsoleExtension;
use Phpactor\Extension\ClassToFile\ClassToFileExtension;
use Phpactor\Extension\Logger\LoggingExtension;
use Phpactor\Extension\Php\PhpExtension;
use Phpactor\Extension\ComposerAutoloader\ComposerAutoloaderExtension;
use Phpactor\MapResolver\Resolver;
use Phpactor\Container\Extension;
use Symfony\Component\Console\Input\InputInterface;
use Phpactor\Extension\ClassToFileExtra\ClassToFileExtraExtension;
use Composer\XdebugHandler\XdebugHandler;
use Phpactor\ConfigLoader\ConfigLoaderBuilder;
use Phpactor\Extension\ReferenceFinderRpc\ReferenceFinderRpcExtension;
use Phpactor\Extension\ReferenceFinder\ReferenceFinderExtension;
use function ini_set;
use function sprintf;

class Phpactor
{
    private const LEGACY_EXTENSIONS = [
        '\Phpactor\Extension\LanguageServerCompletion\LanguageServerCompletionExtension',
        '\Phpactor\Extension\LanguageServer\LanguageServerExtension',
        '\Phpactor\Extension\LanguageServerHover\LanguageServerHoverExtension'
    ];

    public static function boot(InputInterface $input, OutputInterface $output, string $vendorDir): Container
    {
        $config = [];

        $projectRoot = getcwd();

        if ($input->hasParameterOption([ '--working-dir', '-d' ])) {
            $projectRoot = $input->getParameterOption([ '--working-dir', '-d' ]);
        }

        $commandName = $input->getFirstArgument();

        $loader = ConfigLoaderBuilder::create()
            ->enableJsonDeserializer('json')
            ->enableYamlDeserializer('yaml')
            ->addXdgCandidate('phpactor', 'phpactor.json', 'json')
            ->addXdgCandidate('phpactor', 'phpactor.yml', 'yaml')
            ->addCandidate($projectRoot . '/.phpactor.json', 'json')
            ->addCandidate($projectRoot . '/.phpactor.yml', 'yaml')
            ->loader();

        $config = $loader->load();
        $config[CoreExtension::PARAM_COMMAND] = $input->getFirstArgument();
        $config[FilePathResolverExtension::PARAM_APPLICATION_ROOT] = self::resolveApplicationRoot();
        $config = array_merge([ IndexerExtension::PARAM_STUB_PATHS => [] ], $config);
        $config[IndexerExtension::PARAM_STUB_PATHS][] = self::resolveApplicationRoot() . '/vendor/jetbrains/phpstorm-stubs';
        $config = self::configureLanguageServer($config);

        if ($input->hasParameterOption([ '--working-dir', '-d' ])) {
            $config[FilePathResolverExtension::PARAM_PROJECT_ROOT] = $projectRoot;
        }

        if (!isset($config[CoreExtension::PARAM_XDEBUG_DISABLE]) || $config[CoreExtension::PARAM_XDEBUG_DISABLE]) {
            $xdebug = new XdebugHandler('PHPACTOR');
            $xdebug->check();
            unset($xdebug);
        }

        $extensionNames = [
            CoreExtension::class,
            ClassToFileExtraExtension::class,
            ClassToFileExtension::class,
            ClassMoverExtension::class,
            MainClassMoverExtension::class,
            CodeTransformExtension::class,
            CodeTransformExtraExtension::class,
            CompletionExtraExtension::class,
            CompletionWorseExtension::class,
            CompletionExtension::class,
            CompletionRpcExtension::class,
            NavigationExtension::class,
            ContextMenuExtension::class,
            RpcExtension::class,
            SourceCodeFilesystemExtraExtension::class,
            SourceCodeFilesystemExtension::class,
            WorseReflectionExtension::class,
            WorseReflectionExtraExtension::class,
            WorseReflectionAnalyseExtension::class,
            FilePathResolverExtension::class,
            LoggingExtension::class,
            ComposerAutoloaderExtension::class,
            ConsoleExtension::class,
            WorseReferenceFinderExtension::class,
            ReferenceFinderRpcExtension::class,
            ReferenceFinderExtension::class,
            PhpExtension::class,
            LanguageServerExtension::class,
            LanguageServerCompletionExtension::class,
            LanguageServerReferenceFinderExtension::class,
            LanguageServerWorseReflectionExtension::class,
            LanguageServerIndexerExtension::class,
            LanguageServerHoverExtension::class,
            LanguageServerBridgeExtension::class,
            LanguageServerCodeTransformExtension::class,
            LanguageServerSymbolProviderExtension::class,
            LanguageServerSelectionRangeExtension::class,
            LanguageServerExtraExtension::class,
            LanguageServerDiagnosticsExtension::class,
            LanguageServerRenameExtension::class,
            LanguageServerRenameWorseExtension::class,
            LanguageServerPhpstanExtension::class,
            LanguageServerPsalmExtension::class,
            LanguageServerBlackfireExtension::class,
            LanguageServerPhpCsFixerExtension::class,
            BehatExtension::class,
            IndexerExtension::class,
            ObjectRendererExtension::class,
            SymfonyExtension::class,
        ];

        if (class_exists(DebugExtension::class)) {
            $extensionNames[] = DebugExtension::class;
        }

        $container = new PhpactorContainer();

        $container->register('config_loader.candidates', function () use ($loader) {
            return $loader->candidates();
        });

        $masterSchema = new Resolver(true);
        $extensions = [];
        foreach ($extensionNames as $extensionClass) {
            $schema = new Resolver();

            if (!class_exists($extensionClass)) {
                if ($output instanceof ConsoleOutputInterface) {
                    $output->getErrorOutput()->writeln(sprintf('<error>Extension "%s" does not exist</>', $extensionClass). PHP_EOL);
                }
                continue;
            }

            $extension = new $extensionClass();
            if (!$extension instanceof Extension) {
                throw new RuntimeException(sprintf(
                    'Phpactor extension "%s" must implement interface "%s"',
                    get_class($extension),
                    Extension::class
                ));
            }

            $extension->configure($schema);
            $extensions[] = $extension;
            $masterSchema = $masterSchema->merge($schema);
        }
        $masterSchema->setDefaults([
            PhpactorContainer::PARAM_EXTENSION_CLASSES => $extensionNames,

            // enable the LSP watchern
            IndexerExtension::PARAM_ENABLED_WATCHERS => ['lsp', 'inotify', 'find', 'php']
        ]);
        $config = $masterSchema->resolve($config);

        // > method configure container
        foreach ($extensions as $extension) {
            $extension->load($container);
        }

        if (isset($config[CoreExtension::PARAM_MIN_MEMORY_LIMIT])) {
            self::updateMinMemory($config[CoreExtension::PARAM_MIN_MEMORY_LIMIT]);
        }

        foreach ($masterSchema->errors()->errors() as $error) {
            // do not polute STDERR for RPC, for some reason the VIM plugin reads also
            // STDERR and possibly other RPC clients too
            if ($commandName !== 'rpc') {
                if ($output instanceof ConsoleOutputInterface) {
                    $output->getErrorOutput()->writeln(sprintf('<error>%s...</>', substr((string)$error, 0, 100)));
                }
            }
        }

        return $container->build($config);
    }

    /**
     * If the path is relative we need to use the current working path
     * because otherwise it will be the script path, which is wrong in the
     * context of a PHAR.
     *
     * @deprecated Use webmozart Path instead.
     */
    public static function normalizePath(string $path): string
    {
        if (substr($path, 0, 1) == DIRECTORY_SEPARATOR) {
            return $path;
        }

        return getcwd().DIRECTORY_SEPARATOR.$path;
    }

    public static function relativizePath(string $path): string
    {
        if (0 === strpos($path, (string)getcwd())) {
            return substr($path, strlen(getcwd()) + 1);
        }

        return $path;
    }

    public static function isFile(string $string)
    {
        $containsInvalidNamespaceChars = (bool) preg_match('{[\.\*/]}', $string);

        if ($containsInvalidNamespaceChars) {
            return true;
        }

        return file_exists($string);
    }

    /**
     * Optimize Phpactor for the language server (these settings will apply
     * only to LanguageServer sessions).
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private static function configureLanguageServer(array $config): array
    {
        $config[LanguageServerExtension::PARAM_SESSION_PARAMETERS] = [
            LanguageServerExtension::PARAM_METHOD_ALIAS_MAP => [
                'indexer/reindex' => 'phpactor/indexer/reindex',
                'session/dumpConfig' => 'phpactor/debug/config',
                'service/running' => 'phpactor/service/running',
                'system/status' => 'phpactor/stats',
            ],
            WorseReflectionExtension::PARAM_ENABLE_CONTEXT_LOCATION => false,
            ClassToFileExtension::PARAM_BRUTE_FORCE_CONVERSION => false,

            // these completors are not appropriate for the language server SCF
            // is a brute force, blocking completor. the declared completors
            // use the functions declared in the Phpactor runtime and not the
            // project.
            'completion_worse.completor.scf_class.enabled' => false,
            'completion_worse.completor.declared_class.enabled' => false,
            'completion_worse.completor.declared_constant.enabled' => false,
            'completion_worse.completor.declared_function.enabled' => false,
        ];

        return $config;
    }

    private static function resolveApplicationRoot(): string
    {
        $paths = [ __DIR__ . '/..', __DIR__ .'/../../../..' ];

        foreach ($paths as $path) {
            if (is_dir((string)realpath($path.'/vendor'))) {
                return (string)realpath($path);
            }
        }

        throw new RuntimeException(sprintf('Could not resolve application root, tried "%s"', implode('", "', $paths)));
    }

    /**
     * Update the PHP memory limit according to the configured minimum
     * (borrowed from Composer)
     */
    private static function updateMinMemory(int $minimumMemoryLimit): void
    {
        $memoryInBytes = function ($value) {
            $unit = strtolower(substr($value, -1, 1));
            $value = (int) $value;
            switch ($unit) {
                case 'g':
                    $value *= 1024;
                    // no break (cumulative multiplier)
                case 'm':
                    $value *= 1024;
                    // no break (cumulative multiplier)
                case 'k':
                    $value *= 1024;
            }

            return $value;
        };

        $memoryLimit = trim((string)ini_get('memory_limit'));
        if ($memoryLimit != -1 && $memoryInBytes($memoryLimit) < $minimumMemoryLimit) {
            @ini_set('memory_limit', (string)$minimumMemoryLimit);
        }
    }
}
