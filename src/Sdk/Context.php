<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Sdk;

final readonly class Context
{
    public function __construct(
        public string $sdkDir,
        public string $toolchainDir,
        public Process $process,
    ) {
    }
}
