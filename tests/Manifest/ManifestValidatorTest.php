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

    public function test_driver_without_transport(): void
    {
        $problems = (new ManifestValidator())->validate($this->with(
            new Package('php-baremetal/imu-qmi8658', 'bm-driver', '1.0.0', 'p', ['kind' => 'driver', 'bus' => 'i2c', 'driver' => 'qmi8658']),
        ));
        self::assertSame(["php-baremetal/imu-qmi8658: no transport installed for bus 'i2c'"], $problems);
    }

    public function test_driver_with_transport_is_fine(): void
    {
        $problems = (new ManifestValidator())->validate($this->with(
            new Package('php-baremetal/esp32-i2c-transport', 'bm-transport', '1.0.0', 'p', ['kind' => 'transport', 'bus' => 'i2c']),
            new Package('php-baremetal/imu-qmi8658', 'bm-driver', '1.0.0', 'p', ['kind' => 'driver', 'bus' => 'i2c', 'driver' => 'qmi8658']),
        ));
        self::assertSame([], $problems);
    }

    public function test_duplicate_extension_names(): void
    {
        $problems = (new ManifestValidator())->validate($this->with(
            new Package('a/ext-x', 'bm-ext', '1.0.0', 'p', ['kind' => 'ext', 'ext' => 'x']),
            new Package('b/ext-x', 'bm-component', '1.0.0', 'p', ['kind' => 'component', 'name' => 'x']),
        ));
        self::assertSame(["extension 'x' provided by both a/ext-x and b/ext-x"], $problems);
    }

    public function test_driver_with_empty_driver_name(): void
    {
        $problems = (new ManifestValidator())->validate($this->with(
            new Package('php-baremetal/esp32-i2c-transport', 'bm-transport', '1.0.0', 'p', ['kind' => 'transport', 'bus' => 'i2c']),
            new Package('php-baremetal/imu-qmi8658', 'bm-driver', '1.0.0', 'p', ['kind' => 'driver', 'bus' => 'i2c', 'driver' => '']),
        ));
        self::assertSame(["php-baremetal/imu-qmi8658: driver declares no 'driver' name"], $problems);
    }
}
