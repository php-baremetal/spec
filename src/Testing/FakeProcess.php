<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Testing;

use PhpBaremetal\Spec\Sdk\Output;
use PhpBaremetal\Spec\Sdk\Process;
use PhpBaremetal\Spec\Sdk\ProcessResult;

final class FakeProcess implements Process
{
    /** @var list<list<string>> */
    public array $calls = [];

    /** @var array<string,ProcessResult> */
    private array $results = [];

    /** @var array<string,int> */
    private array $ttyResults = [];

    public function on(string $commandPrefix, ProcessResult $result): self
    {
        $this->results[$commandPrefix] = $result;
        return $this;
    }

    public function onTty(string $commandPrefix, int $exitCode): self
    {
        $this->ttyResults[$commandPrefix] = $exitCode;
        return $this;
    }

    public function run(array $command, ?string $cwd = null, array $env = [], ?Output $out = null): ProcessResult
    {
        $this->calls[] = $command;
        $result = $this->match($this->results, $command) ?? new ProcessResult(127, '', 'not faked: ' . implode(' ', $command));
        if ($out !== null) {
            foreach ([$result->output, $result->errorOutput] as $stream) {
                foreach (preg_split('/\R/', rtrim($stream)) ?: [] as $line) {
                    if ($line !== '') {
                        $out->line($line);
                    }
                }
            }
        }
        return $result;
    }

    public function tty(array $command, ?string $cwd = null, array $env = []): int
    {
        $this->calls[] = $command;
        return $this->match($this->ttyResults, $command) ?? 127;
    }

    private function match(array $table, array $command): mixed
    {
        $line = implode(' ', $command);
        $best = null;
        $bestValue = null;
        foreach ($table as $key => $value) {
            $prefix = (string) $key;
            if (str_starts_with($line, $prefix) && ($best === null || strlen($prefix) > strlen($best))) {
                $best = $prefix;
                $bestValue = $value;
            }
        }
        return $bestValue;
    }
}
