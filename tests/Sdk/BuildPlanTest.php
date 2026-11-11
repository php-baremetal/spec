<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Tests\Sdk;

use PhpBaremetal\Spec\Manifest\Manifest;
use PhpBaremetal\Spec\Manifest\Project;
use PhpBaremetal\Spec\Manifest\SdkRequirement;
use PhpBaremetal\Spec\Sdk\BuildPlan;
use PhpBaremetal\Spec\Sdk\PreparedAsset;
use PhpBaremetal\Spec\Sdk\SdkLoader;
use PHPUnit\Framework\TestCase;

final class BuildPlanTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../fixtures/sdk';

    private function plan(array $assets = [], ?string $imageDir = null, bool $named = true): BuildPlan
    {
        $sdk = (new SdkLoader())->load(self::FIXTURE);
        $project = new Project('demo', SdkRequirement::parse('demo@^1.0'), 'demo-board', 'loop', 'flash', '8.4.25', 'code');
        $args = [
            '/work/app',
            new Manifest($project, []),
            $sdk,
            $sdk->board('demo-board'),
            $sdk->chip('demo'),
            $sdk->runtime('loop'),
            $sdk->storage('flash'),
            $sdk->descriptor->release('8.4'),
            '/work/php-src',
            [],
            [],
            '/work/app/.phpbm/build',
        ];
        if (!$named) {
            return new BuildPlan(...$args);
        }

        return new BuildPlan(...$args, assets: $assets, imageDir: $imageDir);
    }

    public function test_assets_and_image_dir_default_to_none(): void
    {
        $plan = $this->plan(named: false);
        self::assertSame([], $plan->assets);
        self::assertNull($plan->imageDir);
        self::assertSame('/work/app/code', $plan->srcDir());
        self::assertSame('/work/app/code', $plan->imageDir());
    }

    public function test_image_dir_overrides_only_the_image(): void
    {
        $plan = $this->plan(imageDir: '/work/app/.phpbm/build/app');
        self::assertSame('/work/app/.phpbm/build/app', $plan->imageDir());
        self::assertSame('/work/app/code', $plan->srcDir());
    }

    public function test_carries_prepared_assets(): void
    {
        $assets = [
            new PreparedAsset('embedded', '/app/assets/icons/play.sli', '/cache/aa.sli'),
            new PreparedAsset('microsd', '/sdcard/photos/beans.sli', '/cache/bb.sli'),
        ];
        $plan = $this->plan($assets);
        self::assertSame($assets, $plan->assets);
        self::assertSame('microsd', $plan->assets[1]->storage);
        self::assertSame('/sdcard/photos/beans.sli', $plan->assets[1]->boardPath);
        self::assertSame('/cache/bb.sli', $plan->assets[1]->file);
    }

    public function test_prepared_asset_is_readonly(): void
    {
        $asset = new PreparedAsset('microsd', '/sdcard/a.sli', '/tmp/a.sli');
        $this->expectException(\Error::class);
        $asset->file = '/tmp/b.sli';
    }
}
