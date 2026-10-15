<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Tests\Sdk;

use PhpBaremetal\Spec\Sdk\Descriptor\Board;
use PhpBaremetal\Spec\Sdk\Descriptor\Chip;
use PhpBaremetal\Spec\Sdk\Descriptor\SdkDescriptor;
use PhpBaremetal\Spec\SpecException;
use PHPUnit\Framework\TestCase;

final class BoardTest extends TestCase
{
    private function board(): Board
    {
        return Board::fromArray('demo-eth', [
            'name' => 'Demo ETH',
            'chip' => 'esp32s3',
            'psram' => 'octal',
            'flash' => '16MB',
            'console' => 'usb-jtag',
            'microsd' => ['type' => 'spi', 'cs' => 4, 'sclk' => 7, 'mosi' => 6, 'miso' => 5],
            'network' => ['type' => 'w5500', 'cs' => 14],
        ], 'boards/demo-eth.json');
    }

    public function test_peripherals_become_capabilities(): void
    {
        self::assertSame(['microsd' => 'spi', 'network' => 'w5500'], $this->board()->capabilities());
        self::assertTrue($this->board()->has('network'));
        self::assertFalse($this->board()->has('display'));
    }

    public function test_overrides_merge_into_a_block(): void
    {
        $board = $this->board()->withOverrides(['microsd' => ['cs' => 5]]);
        self::assertSame(5, $board->peripherals['microsd']['cs']);
        self::assertSame(7, $board->peripherals['microsd']['sclk']);
    }

    public function test_override_of_missing_peripheral_fails(): void
    {
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage("board-overrides: board 'demo-eth' has no 'display'");
        $this->board()->withOverrides(['display' => ['cs' => 1]]);
    }

    public function test_peripheral_needs_a_type(): void
    {
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage("boards/x.json: 'microsd.type' must be a non-empty string");
        Board::fromArray('x', ['name' => 'X', 'chip' => 'c', 'flash' => '4MB', 'microsd' => ['cs' => 1]], 'boards/x.json');
    }

    public function test_override_cannot_change_type(): void
    {
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage("board-overrides: 'microsd.type' cannot be overridden");
        $this->board()->withOverrides(['microsd' => ['type' => 'sdmmc']]);
    }

    public function test_override_block_must_be_an_object(): void
    {
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage("board-overrides: 'microsd' must be an object");
        $this->board()->withOverrides(['microsd' => [1, 2]]);
    }

    public function test_sdk_requires_a_driver_autoload(): void
    {
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage('sdk.json: driver.autoload.psr-4 must map at least one namespace');
        SdkDescriptor::fromArray([
            'name' => 'esp32',
            'version' => '1.0.0',
            'contract' => 1,
            'driver' => ['class' => 'V\\D'],
            'php' => ['8.4' => ['version' => '8.4.25', 'url' => 'u', 'sha256' => str_repeat('a', 64)]],
        ]);
    }

    public function test_chip_supports(): void
    {
        $chip = Chip::fromArray('esp32s3', [
            'name' => 'ESP32-S3',
            'target' => 'esp32s3',
            'capabilities' => ['microsd' => ['spi'], 'network' => ['w5500', 'wifi']],
            'psram' => ['quad', 'octal'],
        ], 'chips/esp32s3/chip.json');
        self::assertTrue($chip->supports('network', 'wifi'));
        self::assertFalse($chip->supports('microsd', 'sdmmc'));
    }

    public function test_sdk_release_lookup_explains_supported_versions(): void
    {
        $sdk = SdkDescriptor::fromArray([
            'name' => 'esp32',
            'version' => '1.0.0',
            'contract' => 1,
            'driver' => ['class' => 'Vendor\\Driver', 'autoload' => ['psr-4' => ['Vendor\\' => 'driver/']]],
            'php' => ['8.4' => ['version' => '8.4.25', 'url' => 'https://x/php.tar.xz', 'sha256' => str_repeat('a', 64)]],
        ]);
        self::assertSame('8.4.25', $sdk->release('8.4')->version);
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage('sdk esp32 1.0.0 does not support PHP 8.2 (supported: 8.4)');
        $sdk->release('8.2');
    }

    public function test_php_release_version_must_match_minor(): void
    {
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage("sdk.json php.8.4: 'version' 8.5.1 is not a 8.4 release");
        SdkDescriptor::fromArray([
            'name' => 'esp32',
            'version' => '1.0.0',
            'contract' => 1,
            'driver' => ['class' => 'V\\D', 'autoload' => ['psr-4' => ['V\\' => 'driver/']]],
            'php' => ['8.4' => ['version' => '8.5.1', 'url' => 'u', 'sha256' => str_repeat('a', 64)]],
        ]);
    }
}
