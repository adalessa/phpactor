<?php

namespace Phpactor\Extension\Laravel\Providers;

use Phpactor\Extension\Laravel\ArtisanRunner;
use Phpactor\WorseReflection\Core\TypeFactory;

class ModelFieldsProvider
{
    public function __construct(
        private readonly ArtisanRunner $artisanRunner,
    ) {
    }

    public function get(string $model): array
    {
        $info = $this->artisanRunner->run('model:show', [$model, '--json']);

        if (null == $info) {
            return [];
        }

        return array_map(
            fn ($field) => [
                'name' => $field['name'],
                'type' => TypeFactory::fromString($field['type'] ?? ''),
            ],
            $info['attributes'],
        );
    }
}
