<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Tests\Manifest;

use PhpBaremetal\Spec\Manifest\AssetDir;
use PhpBaremetal\Spec\Manifest\Assets;
use PhpBaremetal\Spec\Manifest\Project;
use PhpBaremetal\Spec\SpecException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AssetsTest extends TestCase
{
    private const BAREMETAL = [
        'sdk' => 'esp32@^1.0',
        'board' => 'waveshare-esp32-s3-eth',
        'runtime' => 'event-driven',
        'storage' => 'embedded',
    ];

    private function project(mixed $assets): Project
    {
        return Project::fromComposer('acme/blink', [...self::BAREMETAL, 'assets' => $assets], '8.4.25');
    }

    private function assertRejected(mixed $assets, string $message): void
    {
        try {
            $this->project($assets);
            self::fail('expected a SpecException');
        } catch (SpecException $e) {
            self::assertSame($message, $e->getMessage());
        }
    }

    public function test_empty_when_assets_is_missing(): void
    {
        $project = Project::fromComposer('acme/blink', self::BAREMETAL, '8.4.25');
        self::assertTrue($project->assets->isEmpty());
        self::assertSame([], $project->assets->dirs);
        self::assertArrayNotHasKey('assets', $project->toArray());
    }

    public function test_one_entry_is_read(): void
    {
        $assets = $this->project([['dir' => 'assets/ui', 'storage' => 'embedded']])->assets;
        self::assertFalse($assets->isEmpty());
        self::assertCount(1, $assets->dirs);
        self::assertInstanceOf(AssetDir::class, $assets->dirs[0]);
        self::assertSame('assets/ui', $assets->dirs[0]->dir);
        self::assertSame('embedded', $assets->dirs[0]->storage);
    }

    public function test_two_entries_with_different_storages(): void
    {
        $assets = $this->project([
            ['dir' => 'assets/ui', 'storage' => 'embedded'],
            ['dir' => 'assets/photos', 'storage' => 'microsd'],
        ])->assets;
        self::assertCount(2, $assets->dirs);
        self::assertSame('assets/photos', $assets->dirs[1]->dir);
        self::assertSame('microsd', $assets->dirs[1]->storage);
    }

    public function test_dir_is_normalized(): void
    {
        $assets = $this->project([
            ['dir' => 'assets/ui/', 'storage' => 'embedded'],
            ['dir' => './assets\\photos//', 'storage' => 'microsd'],
        ])->assets;
        self::assertSame('assets/ui', $assets->dirs[0]->dir);
        self::assertSame('assets/photos', $assets->dirs[1]->dir);
    }

    public function test_to_array_and_round_trip(): void
    {
        $project = $this->project([
            ['dir' => 'assets/ui', 'storage' => 'embedded'],
            ['dir' => 'assets/photos', 'storage' => 'microsd'],
        ]);
        self::assertSame(
            [['dir' => 'assets/ui', 'storage' => 'embedded'], ['dir' => 'assets/photos', 'storage' => 'microsd']],
            $project->toArray()['assets'],
        );
        $again = Project::fromArray(json_decode(json_encode($project->toArray()), true));
        self::assertEquals($project, $again);
        self::assertSame([], (new Assets())->toArray());
    }

    public function test_assets_must_be_a_list(): void
    {
        $this->assertRejected(['dir' => 'assets', 'storage' => 'embedded'], "extra.baremetal: 'assets' must be a list");
        $this->assertRejected('assets', "extra.baremetal: 'assets' must be a list");
    }

    public function test_entry_must_be_an_object(): void
    {
        $this->assertRejected(['assets/ui'], "extra.baremetal: 'assets[0]' must be an object");
        $this->assertRejected([[]], "extra.baremetal: 'assets[0]' must be an object");
    }

    public function test_unknown_key_is_rejected(): void
    {
        $this->assertRejected(
            [['dir' => 'assets', 'storage' => 'embedded', 'scale' => 2]],
            "extra.baremetal: unknown key 'scale' in assets[0] (allowed: dir, storage)",
        );
    }

    /** @return iterable<string, array{mixed}> */
    public static function badDirs(): iterable
    {
        yield 'absolute' => ['/assets/ui'];
        yield 'windows drive' => ['C:\\assets'];
        yield 'parent' => ['assets/../secrets'];
        yield 'parent only' => ['..'];
        yield 'empty' => [''];
        yield 'dot' => ['.'];
        yield 'not a string' => [12];
        yield 'missing' => [null];
    }

    #[DataProvider('badDirs')]
    public function test_dir_must_be_a_relative_path_inside_the_project(mixed $dir): void
    {
        $this->assertRejected(
            [['dir' => $dir, 'storage' => 'embedded']],
            "extra.baremetal: 'assets[0].dir' must be a non-empty relative path inside the project",
        );
    }

    public function test_storage_must_be_a_non_empty_string(): void
    {
        foreach ([['dir' => 'assets'], ['dir' => 'assets', 'storage' => ''], ['dir' => 'assets', 'storage' => 3]] as $entry) {
            $this->assertRejected([$entry], "extra.baremetal: 'assets[0].storage' must be a non-empty string");
        }
    }

    public function test_duplicate_dir_is_rejected(): void
    {
        $this->assertRejected(
            [['dir' => 'assets', 'storage' => 'embedded'], ['dir' => 'assets/', 'storage' => 'microsd']],
            "extra.baremetal: 'assets[1].dir' ('assets') is the same as 'assets[0].dir' ('assets')",
        );
    }

    public function test_nested_dir_is_rejected(): void
    {
        $this->assertRejected(
            [['dir' => 'assets', 'storage' => 'embedded'], ['dir' => 'assets/ui', 'storage' => 'microsd']],
            "extra.baremetal: 'assets[1].dir' ('assets/ui') is inside 'assets[0].dir' ('assets')",
        );
        $this->assertRejected(
            [['dir' => 'assets/ui', 'storage' => 'embedded'], ['dir' => 'assets', 'storage' => 'microsd']],
            "extra.baremetal: 'assets[0].dir' ('assets/ui') is inside 'assets[1].dir' ('assets')",
        );
    }

    public function test_similar_prefix_is_not_nesting(): void
    {
        $assets = $this->project([
            ['dir' => 'assets', 'storage' => 'embedded'],
            ['dir' => 'assets2', 'storage' => 'microsd'],
        ])->assets;
        self::assertCount(2, $assets->dirs);
    }
}
