<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Testing;

use PhpBaremetal\Spec\Sdk\Output;

final class BufferedOutput implements Output
{
    /** @var list<string> */
    public array $lines = [];

    public function step(string $message): void
    {
        $this->lines[] = 'step: ' . $message;
    }

    public function line(string $message): void
    {
        $this->lines[] = 'line: ' . $message;
    }

    public function warning(string $message): void
    {
        $this->lines[] = 'warning: ' . $message;
    }
}
