<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Sdk;

interface Process
{
    /**
     * Never throws on a non-zero exit; the output is always captured in the result and, when $out is given, also forwarded line by line.
     *
     * @param list<string>          $command
     * @param array<string,string>  $env
     */
    public function run(array $command, ?string $cwd = null, array $env = [], ?Output $out = null): ProcessResult;

    /**
     * @param list<string>          $command
     * @param array<string,string>  $env
     */
    public function tty(array $command, ?string $cwd = null, array $env = []): int;
}
