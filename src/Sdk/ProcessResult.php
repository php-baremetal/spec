<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Sdk;

final readonly class ProcessResult
{
    public function __construct(
        public int $exitCode,
        public string $output = '',
        public string $errorOutput = '',
    ) {
    }

    public function successful(): bool
    {
        return $this->exitCode === 0;
    }
}
