<?php

namespace Phpactor\Extension\Laravel\Providers;

use Symfony\Component\Process\Process;

class RoutesProvider
{
    public function get(): array
    {
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
    }
}
