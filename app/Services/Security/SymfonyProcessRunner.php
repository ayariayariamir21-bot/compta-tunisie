<?php

namespace App\Services\Security;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Executes external commands (pg_dump, pg_restore, git) with argument
 * arrays so no user input is ever interpolated into a shell string.
 */
final class SymfonyProcessRunner implements ProcessRunner
{
    public function run(array $command, array $env = [], ?int $timeout = null): ProcessOutcome
    {
        $process = new Process($command, base_path(), $env, null, $timeout ?? config('backups.timeout', 900));

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            return new ProcessOutcome($command, false, -1, '', 'Le processus a dépassé le temps imparti.');
        }

        return new ProcessOutcome(
            $command,
            $process->isSuccessful(),
            $process->getExitCode() ?? -1,
            $process->getOutput(),
            $process->getErrorOutput(),
        );
    }
}
