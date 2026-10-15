<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Testing;

use PhpBaremetal\Spec\Sdk\Context;
use PhpBaremetal\Spec\Sdk\Driver;
use PhpBaremetal\Spec\Sdk\Port;
use PhpBaremetal\Spec\Sdk\SdkLoader;
use PhpBaremetal\Spec\Sdk\ToolchainStatus;
use PHPUnit\Framework\TestCase;

abstract class DriverConformanceTestCase extends TestCase
{
    abstract protected function sdkDir(): string;

    protected function driver(?FakeProcess $process = null): Driver
    {
        $sdk = (new SdkLoader())->load($this->sdkDir());
        $toolchainDir = sys_get_temp_dir() . '/phpbm-conformance-' . bin2hex(random_bytes(4));

        return $sdk->driver(new Context($sdk->dir, $toolchainDir, $process ?? new FakeProcess()));
    }

    public function test_sdk_targets_the_current_contract(): void
    {
        self::assertSame(Driver::CONTRACT, (new SdkLoader())->load($this->sdkDir())->descriptor->contract);
    }

    public function test_driver_class_implements_the_contract(): void
    {
        self::assertInstanceOf(Driver::class, $this->driver());
    }

    public function test_toolchain_reports_a_status(): void
    {
        self::assertInstanceOf(ToolchainStatus::class, $this->driver()->toolchain()->status());
    }

    public function test_ports_are_ports(): void
    {
        foreach ($this->driver()->ports() as $port) {
            self::assertInstanceOf(Port::class, $port);
        }
        $this->addToAssertionCount(1);
    }

    public function test_identify_returns_null_when_nothing_answers(): void
    {
        self::assertNull($this->driver()->identify(new Port('/dev/phpbm-nothing')));
    }
}
