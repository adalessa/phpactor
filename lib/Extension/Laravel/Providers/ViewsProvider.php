<?php

namespace Phpactor\Extension\Laravel\Providers;

use Phpactor\WorseReflection\Core\Cache;
use Symfony\Component\Finder\Finder;
use function getcwd;

class ViewsProvider
{
    public function __construct(
        private readonly Cache $cache,
    ) {
    }

    public function get(): array
    {
        return $this->cache->getOrSet('laravel-views', function(): array {
            $cwd = getcwd();
            if (null === $cwd) {
                return [];
            }

            $files = (new Finder())->files()
                ->in($cwd . '/resources/views')
                ->name('*.blade.php')
                ->getIterator()
            ;

            $viewNames = [];

            foreach($files as $file) {
                $viewName = str_replace('.blade.php', '', $file->getRelativePathname());
                if (strpos($viewName, '/') !== false) {
                    // Laravel routes use . in place of /
                    $viewName = str_replace('/', '.', $viewName);
                }

                $viewNames[$viewName] = $file->getRelativePathname();
            }

            return $viewNames;
        });
    }
}