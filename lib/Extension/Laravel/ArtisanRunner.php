<?php

namespace Phpactor\Extension\Laravel;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

class ArtisanRunner
{
    /** @var string[] */
    private array $command;

    public function __construct(
        string $command,
        private readonly LoggerInterface $logger,
    ) {
        $this->command = explode(' ', $command);
    }

    /**
     * @param string[] $args
     *
     * @return ($json is true ? array : string)
     */
    public function run(string $command, array $args = [], bool $json = true)
    {
        $process = new Process([...$this->command, $command, ...$args]);

        $process->run();

        if (!$process->isSuccessful()) {
            $this->logger->warning(
                'error running command ' . $command,
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
