<?php

namespace Phpactor\Extension\Laravel;

use Symfony\Component\Process\Process;

class ArtisanRunner
{
    /** @var string[] */
    private array $command;

    public function __construct(string $command)
    {
        $this->command = explode(' ', $command);
    }

    public function routeList(): array
    {
        $a = $this->run('routes:list', ['--json']);

        return $a;
    }

    /**
     * @param string[] $args
     *
     * @return ($json is true ? array : string)
     */
    public function run(string $command, array $args = [], bool $json = true)
    {
        $process = new Process(
            $this->command, + [$command] + $args
        );

        $process->run();

        if (!$process->isSuccessful()) {
            return null;
        }

        if ($json) {
            return json_decode($process->getOutput(), true);
        } else {
            return $process->getOutput();
        }
    }
}
