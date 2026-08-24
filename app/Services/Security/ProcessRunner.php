<?php

namespace App\Services\Security;

interface ProcessRunner
{
    /**
     * Run a command with argument-array semantics (never shell strings).
     *
     * @param  list<string>  $command
     * @param  array<string, string>  $env  additional environment variables merged into the inherited environment
     */
    public function run(array $command, array $env = [], ?int $timeout = null): ProcessOutcome;
}
