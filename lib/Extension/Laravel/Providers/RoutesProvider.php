<?php

namespace Phpactor\Extension\Laravel\Providers;

use Phpactor\Extension\Laravel\ArtisanRunner;
use Phpactor\WorseReflection\Core\Cache;
use Symfony\Component\Process\Process;

class RoutesProvider
{
    public function __construct(
        private readonly ArtisanRunner $artisanRunner,
        private readonly Cache $cache,
    ) {
    }

    public function get(): array
    {
        return $this->cache->getOrSet('laravel-routes', function(): array {
            $routes = $this->artisanRunner->run('routes:list', ['--json']);

            $process = new Process(['php', 'artisan', 'route:list', '--json']);
            $process->run();
            if (!$process->isSuccessful()) {
                return [];
            }
            $routes = json_decode($process->getOutput(), true);

            return array_map(function ($route) {
                return [
                    'name' => $route['name'],
                    'path' => $route['uri'],
                ];
            }, $routes);
        });
    }
}
