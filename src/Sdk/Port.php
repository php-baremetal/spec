<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Sdk;

final readonly class Port
{
    public function __construct(
        public string $path,
        public ?string $description = null,
        public ?string $usbId = null,
    ) {
    }
}
