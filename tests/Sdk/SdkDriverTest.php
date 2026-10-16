<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Tests\Sdk;

use PhpBaremetal\Spec\Sdk\Context;
use PhpBaremetal\Spec\Sdk\Driver;
use PhpBaremetal\Spec\Sdk\SdkLoader;
use PhpBaremetal\Spec\SpecException;
use PhpBaremetal\Spec\Testing\BufferedOutput;
use PhpBaremetal\Spec\Testing\FakeProcess;
use PHPUnit\Framework\TestCase;

final class SdkDriverTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../fixtures/sdk';

    /** @var list<string> */
    private array $temp = [];

    private static function delete(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }

    private static function copy(string $from, string $to): void
    {
        mkdir($to, 0777, true);
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($items as $item) {
            $target = $to . '/' . $items->getSubPathname();
            $item->isDir() ? mkdir($target, 0777, true) : copy($item->getPathname(), $target);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->temp as $dir) {
            self::delete($dir);
        }
    }

    private function context(string $dir): Context
    {
        return new Context($dir, sys_get_temp_dir() . '/phpbm-sdk-driver', new FakeProcess());
    }

    private function modified(callable $edit): string
    {
        $dir = sys_get_temp_dir() . '/phpbm-sdk-' . bin2hex(random_bytes(4));
        $this->temp[] = $dir;
        self::copy(self::FIXTURE, $dir);
        $json = json_decode((string) file_get_contents($dir . '/sdk.json'), true);
        $edit($json);
        file_put_contents($dir . '/sdk.json', json_encode($json));
        return $dir;
    }

    public function test_loads_the_driver(): void
    {
        $sdk = (new SdkLoader())->load(self::FIXTURE);
        $driver = $sdk->driver($this->context($sdk->dir));
        self::assertInstanceOf(Driver::class, $driver);
        self::assertSame($driver::class, $sdk->descriptor->driverClass);
        self::assertInstanceOf(Driver::class, $sdk->driver($this->context($sdk->dir)));
    }

    public function test_rejects_an_unsupported_contract(): void
    {
        $dir = $this->modified(static function (array &$json): void {
            $json['contract'] = 99;
        });
        $sdk = (new SdkLoader())->load($dir);
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage('sdk demo 1.2.0: contract 99 is not supported (expected ' . Driver::CONTRACT . ')');
        $sdk->driver($this->context($dir));
    }

    public function test_reports_a_missing_class(): void
    {
        $dir = $this->modified(static function (array &$json): void {
            $json['driver']['class'] = 'PhpBaremetal\\Spec\\Tests\\Fixtures\\Nope';
        });
        $sdk = (new SdkLoader())->load($dir);
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage('sdk demo 1.2.0: driver class PhpBaremetal\Spec\Tests\Fixtures\Nope not found');
        $sdk->driver($this->context($dir));
    }

    public function test_rejects_a_class_that_is_not_a_driver(): void
    {
        $dir = $this->modified(static function (array &$json): void {
            $json['driver']['class'] = BufferedOutput::class;
        });
        $sdk = (new SdkLoader())->load($dir);
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage('sdk demo 1.2.0: ' . BufferedOutput::class . ' does not implement ' . Driver::class);
        $sdk->driver($this->context($dir));
    }
}
