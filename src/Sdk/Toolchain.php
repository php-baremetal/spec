<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Sdk;

interface Toolchain
{
    public function status(): ToolchainStatus;

    public function install(Output $out): void;

    /** @return array<string,string> */
    public function env(): array;
}
