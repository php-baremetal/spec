<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Tests\Manifest;

use PhpBaremetal\Spec\Manifest\Manifest;
use PhpBaremetal\Spec\Manifest\Package;
use PhpBaremetal\Spec\Manifest\Project;
use PhpBaremetal\Spec\SpecException;
use PHPUnit\Framework\TestCase;

final class ManifestTest extends TestCase
{
    public static function sample(): Manifest
    {
        $project = Project::fromComposer('acme/blink', [
            'sdk' => 'esp32@^1.0',
            'board' => 'demo-board',
            'runtime' => 'init-loop',
            'storage' => 'embedded',
        ], '8.4.25');

        return new Manifest($project, [
            new Package('php-baremetal/ext-gpio', 'bm-ext', '1.0.0', '.phpbm/packages/php-baremetal/ext-gpio', ['kind' => 'ext', 'ext' => 'gpio']),
            new Package('php-baremetal/esp32-gpio-hal', 'bm-hal', '1.0.0', '.phpbm/packages/php-baremetal/esp32-gpio-hal', ['hal' => 'gpio']),
        ]);
    }

    public function test_round_trip_through_json(): void
    {
        $manifest = self::sample();
        $again = Manifest::fromArray(json_decode($manifest->toJson(), true));
        self::assertEquals($manifest, $again);
    }

    public function test_kind_falls_back_to_type(): void
    {
        self::assertSame('hal', self::sample()->packages[1]->kind());
        self::assertCount(1, self::sample()->ofKind('ext'));
    }

    public function test_has(): void
    {
        self::assertTrue(self::sample()->has('php-baremetal/ext-gpio'));
        self::assertFalse(self::sample()->has('php-baremetal/ext-web'));
    }

    public function test_rejects_unknown_schema(): void
    {
        $data = self::sample()->toArray();
        $data['schema'] = 99;
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage('unsupported schema');
        Manifest::fromArray(json_decode(json_encode($data), true));
    }

    public function test_rejects_non_bm_package_type(): void
    {
        $data = json_decode(self::sample()->toJson(), true);
        $data['packages'][0]['type'] = 'library';
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage("manifest.packages[0]: 'type' must start with 'bm-'");
        Manifest::fromArray($data);
    }

    public function test_from_file(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'manifest');
        file_put_contents($file, self::sample()->toJson());
        self::assertEquals(self::sample(), Manifest::fromFile($file));
    }
}
