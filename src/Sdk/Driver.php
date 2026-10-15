<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Sdk;

interface Driver
{
    public const CONTRACT = 1;

    public function __construct(Context $context);

    public function toolchain(): Toolchain;

    public function build(BuildPlan $plan, Output $out): Artifacts;

    public function flash(Artifacts $artifacts, BuildPlan $plan, Port $port, Output $out): void;

    public function monitor(Port $port): int;

    /** Null when the chip on the port does not belong to this platform. */
    public function identify(Port $port): ?ChipInfo;

    /** @return list<Port> */
    public function ports(): array;
}
