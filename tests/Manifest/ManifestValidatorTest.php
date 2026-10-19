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
}
