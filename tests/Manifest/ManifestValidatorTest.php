<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Tests\Manifest;

use PhpBaremetal\Spec\Manifest\Manifest;
use PhpBaremetal\Spec\Manifest\ManifestValidator;
use PhpBaremetal\Spec\Manifest\Package;
use PHPUnit\Framework\TestCase;

final class ManifestValidatorTest extends TestCase
{
    private function with(Package ...$packages): Manifest
    {
        return new Manifest(ManifestTest::sample()->project, $packages);
    }

    public function test_valid_manifest_has_no_problems(): void
    {
        self::assertSame([], (new ManifestValidator())->validate(ManifestTest::sample()));
    }

    public function test_duplicate_extension_names(): void
    {
        $problems = (new ManifestValidator())->validate($this->with(
            new Package('a/ext-x', 'bm-ext', '1.0.0', 'p', ['module' => 'x']),
            new Package('b/ext-x', 'bm-ext', '1.0.0', 'p', ['module' => 'x']),
        ));
        self::assertSame(["extension 'x' provided by both a/ext-x and b/ext-x"], $problems);
    }

    public function test_extension_without_module(): void
    {
        $problems = (new ManifestValidator())->validate($this->with(
            new Package('a/ext-x', 'bm-ext', '1.0.0', 'p', []),
        ));
        self::assertSame(["a/ext-x: extension declares no 'module'"], $problems);
    }

    public function test_kind_ignores_meta(): void
    {
        self::assertSame('ext', (new Package('a/ext-x', 'bm-ext', '1.0.0', 'p', ['kind' => 'hal']))->kind());
    }

    public function test_php_extension_packages_carry_only_php_ext(): void
    {
        $validator = new ManifestValidator();
        self::assertSame([], $validator->validate($this->with(
            new Package('php-baremetal/php-mbstring', 'bm-php-ext', '1.0.0', 'p', ['php-ext' => 'mbstring']),
        )));
        self::assertSame('php-ext', (new Package('a/php-x', 'bm-php-ext', '1.0.0', 'p', []))->kind());
        self::assertSame([
            "a/php-x: type bm-php-ext needs 'php-ext' in extra.baremetal",
            "b/php-y: type bm-php-ext allows only 'php-ext' in extra.baremetal, got 'sources'",
            "c/php-z: 'php-ext' must name a PHP extension ([a-z][a-z0-9_]*)",
        ], $validator->validate($this->with(
            new Package('a/php-x', 'bm-php-ext', '1.0.0', 'p', []),
            new Package('b/php-y', 'bm-php-ext', '1.0.0', 'p', ['php-ext' => 'y', 'sources' => ['a.c']]),
            new Package('c/php-z', 'bm-php-ext', '1.0.0', 'p', ['php-ext' => 'Z-1']),
        )));
    }

    public function test_board_blocks_are_a_list_of_kind_type_strings(): void
    {
        self::assertSame([], (new ManifestValidator())->validate($this->with(
            new Package('php-baremetal/led-ws2812', 'bm-lib', '1.0.0', 'p', ['board-blocks' => ['led:ws2812', 'i2c:i2c', 'display:st77916-qspi']]),
        )));
    }

    public function test_board_blocks_must_be_a_list_of_kind_type_strings(): void
    {
        $message = "php-baremetal/led-ws2812: extra.baremetal.board-blocks must be a list of '<kind>:<type>' strings";
        foreach (['led:ws2812', ['led'], ['Led:ws2812'], [1], ['led-strip:ws2812'], ['led:-ws2812'], ['led:ws_2812'], ["led:ws2812\n"]] as $blocks) {
            self::assertSame([$message], (new ManifestValidator())->validate($this->with(
                new Package('php-baremetal/led-ws2812', 'bm-lib', '1.0.0', 'p', ['board-blocks' => $blocks]),
            )));
        }
    }

    public function test_ide_stubs_are_a_list_of_relative_stub_paths(): void
    {
        self::assertSame([], (new ManifestValidator())->validate($this->with(
            new Package('php-baremetal/ext-x', 'bm-lib', '1.0.0', 'p', ['ide-stubs' => ['ide/x.stub', 'ide/sub/y.stub']]),
        )));
    }

    public function test_ide_stubs_must_be_a_list_of_relative_stub_paths(): void
    {
        $message = 'php-baremetal/ext-x: extra.baremetal.ide-stubs must be a list of relative paths ending in .stub';
        foreach (['ide/x.stub', ['/abs/x.stub'], ['C:/x.stub'], ['ide\\..\\x.stub'], ['../x.stub'], ['ide/../x.stub'], ['ide/x.php'], [1], [''], ['ide/x.stub', 2]] as $stubs) {
            self::assertSame([$message], (new ManifestValidator())->validate($this->with(
                new Package('php-baremetal/ext-x', 'bm-lib', '1.0.0', 'p', ['ide-stubs' => $stubs]),
            )));
        }
    }

    public function test_hal_platform_must_match_the_project_sdk(): void
    {
        $validator = new ManifestValidator();
        self::assertSame([
            "php-baremetal/esp32-a-hal: type bm-hal needs 'platform' in extra.baremetal",
            "php-baremetal/stm32-b-hal: 'platform' is 'stm32' but the project's sdk is 'esp32'",
            "php-baremetal/esp32-c-hal: 'platform' is 'int' but the project's sdk is 'esp32'",
        ], $validator->validate($this->with(
            new Package('php-baremetal/esp32-a-hal', 'bm-hal', '1.0.0', 'p', []),
            new Package('php-baremetal/stm32-b-hal', 'bm-hal', '1.0.0', 'p', ['platform' => 'stm32']),
            new Package('php-baremetal/esp32-c-hal', 'bm-hal', '1.0.0', 'p', ['platform' => 1]),
            new Package('php-baremetal/esp32-d-hal', 'bm-hal', '1.0.0', 'p', ['platform' => 'esp32']),
        )));
    }

    public function test_package_options_are_validated_and_exposed(): void
    {
        $options = [
            'events-small' => ['description' => 'Small queue', 'type' => 'int', 'min' => 1, 'max' => 64, 'default' => 8],
            'events-mode' => ['description' => 'Mode', 'values' => ['fast', 'safe'], 'default' => 'safe'],
            'events-trace' => ['description' => 'Trace', 'type' => 'bool', 'default' => false],
        ];
        $ext = new Package('php-baremetal/ext-events', 'bm-ext', '1.0.0', 'p', ['module' => 'events', 'options' => $options]);
        self::assertSame([], (new ManifestValidator())->validate($this->with($ext)));
        self::assertSame(['events-small', 'events-mode', 'events-trace'], array_keys($ext->options()));
        self::assertSame(8, $ext->options()['events-small']->default);
        self::assertSame([], (new Package('a/ext-x', 'bm-ext', '1.0.0', 'p', ['module' => 'x']))->options());
    }

    public function test_package_option_definitions_must_be_valid(): void
    {
        $problems = (new ManifestValidator())->validate($this->with(
            new Package('a/ext-x', 'bm-ext', '1.0.0', 'p', ['module' => 'x', 'options' => ['x-a' => ['description' => 'A', 'type' => 'int']]]),
            new Package('a/ext-y', 'bm-ext', '1.0.0', 'p', ['module' => 'y', 'options' => ['y-a' => ['description' => 'A', 'type' => 'float', 'default' => 1]]]),
        ));
        self::assertSame([
            "a/ext-x: extra.baremetal.options.x-a: 'default' is required",
            "a/ext-y: extra.baremetal.options.y-a: 'type' must be int, bool or string, or list the allowed 'values'",
        ], $problems);
    }

    public function test_package_options_need_the_module_prefix(): void
    {
        $opt = ['description' => 'A', 'type' => 'bool', 'default' => true];
        $problems = (new ManifestValidator())->validate($this->with(
            new Package('a/ext-events', 'bm-ext', '1.0.0', 'p', ['module' => 'events', 'options' => ['small' => $opt, 'events-small' => $opt, 'eventsx-a' => $opt]]),
            new Package('php-baremetal/esp32-events-hal', 'bm-hal', '1.0.0', 'p', ['platform' => 'esp32', 'options' => ['events-hal-x' => $opt, 'events-x' => $opt]]),
            new Package('php-baremetal/led-ws2812', 'bm-lib', '1.0.0', 'p', ['options' => ['led-ws2812-n' => $opt, 'other' => $opt]]),
        ));
        self::assertSame([
            "a/ext-events: option 'small' must start with 'events-'",
            "a/ext-events: option 'eventsx-a' must start with 'events-'",
            "php-baremetal/esp32-events-hal: option 'events-x' must start with 'events-hal-'",
            "php-baremetal/led-ws2812: option 'other' must start with 'led-ws2812-'",
        ], $problems);
    }
}
