<?php

namespace App\Support;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class ProcessRunner
{
    /**
     * Runs an untimed command to completion, throwing if it fails.
     *
     * @param  array<int, string>  $command
     * @param  array<string, string>|null  $env
     */
    public static function run(array $command, ?array $env = null): Process
    {
        $process = new Process($command, env: $env);
        $process->setTimeout(null);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process;
    }
}
