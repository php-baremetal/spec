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

    public function test_flash_mode_and_revision_are_board_fields(): void
    {
        self::assertNull($this->board()->flashMode);
        self::assertNull($this->board()->revision);
        $board = Board::fromArray('p4', ['name' => 'P4', 'chip' => 'esp32p4', 'flash' => '32MB', 'flash-mode' => 'quad', 'revision' => 'v3'], 'boards/p4.json');
        self::assertSame('quad', $board->flashMode);
        self::assertSame('v3', $board->revision);
        self::assertSame([], $board->capabilities());
        self::assertSame('v3', $board->withOverrides([])->revision);
    }

    public function test_override_of_missing_peripheral_without_type_fails(): void
    {
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage("board-overrides: board 'demo-eth' has no 'display' to override: to add the block, give its 'type'");
        $this->board()->withOverrides(['display' => ['cs' => 1]]);
    }

    public function test_override_with_a_type_adds_a_block_the_board_lacks(): void
    {
        $generic = Board::fromArray('generic', ['name' => 'Generic', 'chip' => 'esp32s3', 'flash' => '8MB', 'network' => ['type' => 'wifi']], 'boards/generic.json');
        $board = $generic->withOverrides(['microsd' => ['type' => 'spi', 'cs' => 10, 'sclk' => 12, 'mosi' => 11, 'miso' => 13]]);
        self::assertTrue($board->has('microsd'));
        self::assertSame(['type' => 'spi', 'cs' => 10, 'sclk' => 12, 'mosi' => 11, 'miso' => 13], $board->peripherals['microsd']);
        self::assertSame(['network' => 'wifi', 'microsd' => 'spi'], $board->capabilities());
        self::assertFalse($generic->has('microsd'), 'the board itself is not changed');
    }

    public function test_an_added_block_needs_a_non_empty_string_type(): void
    {
        foreach ([['type' => ''], ['type' => 3], ['type' => null]] as $bad) {
            try {
                $this->board()->withOverrides(['display' => [...$bad, 'cs' => 1]]);
                self::fail('accepted ' . json_encode($bad));
            } catch (SpecException $e) {
                self::assertSame("board-overrides: 'display.type' must be a non-empty string", $e->getMessage());
            }
        }
    }

    public function test_an_added_block_needs_a_block_name(): void
    {
        foreach (['Net', 'led_2', 'led 2', '-led', 'led-', '2led', ''] as $bad) {
            try {
                $this->board()->withOverrides([$bad => ['type' => 'ws2812']]);
                self::fail("accepted '{$bad}'");
            } catch (SpecException $e) {
                self::assertSame("board-overrides: '{$bad}' is not a block name (lower-case letters and digits, parts joined by '-', e.g. led-2)", $e->getMessage());
            }
        }
        self::assertTrue($this->board()->withOverrides(['led-2' => ['type' => 'ws2812']])->has('led-2'));
    }

    public function test_board_fields_cannot_be_added_as_blocks(): void
    {
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage("board-overrides: board 'demo-eth' has no 'flash' to override");
        $this->board()->withOverrides(['flash' => ['type' => 'x']]);
    }

    public function test_the_same_type_of_an_existing_block_is_accepted(): void
    {
        $board = $this->board()->withOverrides(['microsd' => ['type' => 'spi', 'cs' => 9]]);
        self::assertSame('spi', $board->peripherals['microsd']['type']);
        self::assertSame(9, $board->peripherals['microsd']['cs']);
        self::assertSame(7, $board->peripherals['microsd']['sclk']);
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
        $this->expectExceptionMessage("board-overrides: 'microsd.type' cannot be overridden: board 'demo-eth' has a 'spi' microsd");
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
        self::assertNull($sdk->release('8.4')->startupKb);
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage('sdk esp32 1.0.0 does not support PHP 8.2 (supported: 8.4)');
        $sdk->release('8.2');
    }

    public function test_php_release_startup_kb(): void
    {
        $release = static fn (mixed $kb): SdkDescriptor => SdkDescriptor::fromArray([
            'name' => 'esp32',
            'version' => '1.0.0',
            'contract' => 1,
            'driver' => ['class' => 'V\\D', 'autoload' => ['psr-4' => ['V\\' => 'driver/']]],
            'php' => ['8.4' => ['version' => '8.4.1', 'url' => 'u', 'sha256' => str_repeat('a', 64), 'startup-kb' => $kb]],
        ]);
        self::assertSame(816, $release(816)->release('8.4')->startupKb);
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage("sdk.json php.8.4: 'startup-kb' must be >= 0");
        $release(-1);
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
