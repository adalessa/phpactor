<?php

namespace Phpactor\Extension\Laravel;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

class ArtisanRunner
{
    public function __construct(
        private readonly string $command,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param string[] $args
     *
     * @return ($json is true ? array : string)
     */
    public function run(string $subCommand, array $args = [], bool $json = true)
    {
        $process = new Process([...explode($this->command, ' '), $subCommand, ...$args]);

        $process->run();

        if (!$process->isSuccessful()) {
            $this->logger->warning(
                'error running command ' . $subCommand,
                [
                    'command' => $process->getCommandLine(),
                    'error' => $process->getErrorOutput(),
                    'output' => $process->getOutput(),
                ],
            );
            return null;
        }

        if ($json) {
            return json_decode($process->getOutput(), true);
        } else {
            return $process->getOutput();
        }
    }
}
