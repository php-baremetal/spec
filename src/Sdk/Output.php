<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Sdk;

interface Output
{
    public function step(string $message): void;

    public function line(string $message): void;

    public function warning(string $message): void;
}
