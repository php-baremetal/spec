<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Tests\Fixtures;

use PhpBaremetal\Spec\Sdk\Artifacts;
use PhpBaremetal\Spec\Sdk\BuildPlan;
use PhpBaremetal\Spec\Sdk\ChipInfo;
use PhpBaremetal\Spec\Sdk\Context;
use PhpBaremetal\Spec\Sdk\Driver;
use PhpBaremetal\Spec\Sdk\Output;
use PhpBaremetal\Spec\Sdk\Port;
use PhpBaremetal\Spec\Sdk\Toolchain;
use PhpBaremetal\Spec\Sdk\ToolchainStatus;

final class NullDriver implements Driver
{
    public function __construct(private readonly Context $context)
    {
    }

    public function toolchain(): Toolchain
    {
        return new class () implements Toolchain {
            public function status(): ToolchainStatus
            {
                return new ToolchainStatus(true, '0.0.0');
            }

            public function install(Output $out): void
            {
            }

            public function env(): array
            {
                return [];
            }
        };
    }

    public function build(BuildPlan $plan, Output $out): Artifacts
    {
        return new Artifacts($plan->buildDir, []);
    }

    public function flash(Artifacts $artifacts, BuildPlan $plan, Port $port, Output $out): void
    {
    }

    public function monitor(Port $port): int
    {
        return $this->context->process->tty(['cat', $port->path]);
    }

    public function identify(Port $port): ?ChipInfo
    {
        $result = $this->context->process->run(['identify', $port->path]);
        return $result->successful() ? new ChipInfo(trim($result->output), 'Demo') : null;
    }

    public function ports(): array
    {
        return [];
    }
}
