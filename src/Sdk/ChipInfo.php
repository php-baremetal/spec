<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Sdk;

final readonly class ChipInfo
{
    /** @param list<string> $features */
    public function __construct(
        public string $target,
        public string $name,
        public ?string $revision = null,
        public ?string $flashSize = null,
        public ?string $mac = null,
        public array $features = [],
    ) {
    }
}
