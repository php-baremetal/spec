<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Sdk;

final readonly class ToolchainStatus
{
    public function __construct(
        public bool $installed,
        public ?string $version = null,
        public ?string $problem = null,
    ) {
    }
}
