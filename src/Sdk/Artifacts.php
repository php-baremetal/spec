<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Sdk;

final readonly class Artifacts
{
    /**
     * @param array<int,string>    $images offset => absolute path
     * @param array<string,mixed>  $extra  driver-specific data handed back to flash()
     */
    public function __construct(
        public string $buildDir,
        public array $images,
        public array $extra = [],
    ) {
    }
}
