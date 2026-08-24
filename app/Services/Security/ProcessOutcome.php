<?php

namespace App\Services\Security;

/**
 * Immutable result of one external process execution.
 */
final class ProcessOutcome
{
    /**
     * @param  list<string>  $command
     */
    public function __construct(
        public readonly array $command,
        public readonly bool $successful,
        public readonly int $exitCode,
        public readonly string $output,
        public readonly string $error,
    ) {}
}
