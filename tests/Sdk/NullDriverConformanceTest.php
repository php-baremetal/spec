<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Tests\Sdk;

use PhpBaremetal\Spec\Sdk\ProcessResult;
use PhpBaremetal\Spec\Sdk\Port;
use PhpBaremetal\Spec\Testing\BufferedOutput;
use PhpBaremetal\Spec\Testing\DriverConformanceTestCase;
use PhpBaremetal\Spec\Testing\FakeProcess;

final class NullDriverConformanceTest extends DriverConformanceTestCase
{
    protected function sdkDir(): string
    {
        return __DIR__ . '/../fixtures/sdk';
    }

    public function test_fake_process_records_and_answers(): void
    {
        $process = (new FakeProcess())->on('identify /dev/x', new ProcessResult(0, "demo\n"));
        $chip = $this->driver($process)->identify(new Port('/dev/x'));
        self::assertSame('demo', $chip?->target);
        self::assertSame([['identify', '/dev/x']], $process->calls);
    }

    public function test_fake_process_accepts_numeric_prefixes(): void
    {
        $process = (new FakeProcess())->on('123', new ProcessResult(0, 'ok'));
        self::assertSame('ok', $process->run(['123', 'x'])->output);
    }

    public function test_fake_process_forwards_stdout_and_stderr(): void
    {
        $out = new BufferedOutput();
        (new FakeProcess())->on('tool', new ProcessResult(1, "a\n", "b\n"))->run(['tool'], null, [], $out);
        self::assertSame(['line: a', 'line: b'], $out->lines);
    }
}
