<?php

namespace Phpactor\Extension\Laravel\Providers;

use Phpactor\Extension\Laravel\ArtisanRunner;
use Phpactor\WorseReflection\Core\Cache;

class ConfigsProvider
{
    public function __construct(
        private readonly ArtisanRunner $artisanRunner,
        private readonly Cache $cache,
    ) {
    }

    public function get(): array
    {
        return $this->cache->getOrSet('laravel-configs', function(): array {
            return $this->artisanRunner->run(
                'tinker',
                [
                    '--execute',
                    'echo json_encode(collect(\Illuminate\Support\Arr::dot(\Illuminate\Support\Facades\Config::all()))->keys()->filter(fn($key) => is_string($key))->values()->all());'
                ]
            );
        });
    }
}
