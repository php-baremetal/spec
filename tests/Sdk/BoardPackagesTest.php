<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Tests\Sdk;

use PhpBaremetal\Spec\Manifest\Package;
use PhpBaremetal\Spec\Sdk\Descriptor\Board;
use PhpBaremetal\Spec\Sdk\Sdk;
use PhpBaremetal\Spec\Sdk\SdkLoader;
use PhpBaremetal\Spec\SpecException;
use PHPUnit\Framework\TestCase;

final class BoardPackagesTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../fixtures/sdk';

    private string $project;

    protected function setUp(): void
    {
        $this->project = sys_get_temp_dir() . '/spec-boards-' . bin2hex(random_bytes(4));
        mkdir($this->project);
    }

    protected function tearDown(): void
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->project, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $file) {
            $file->isDir() ? rmdir((string) $file) : unlink((string) $file);
        }
        rmdir($this->project);
    }

    /** A bm-boards package under the project, with files relative to the package (e.g. boards/demo/x.json). */
    private function package(string $name, array $files): Package
    {
        $path = '.phpbm/packages/' . $name;
        foreach ($files as $file => $json) {
            $target = "{$this->project}/{$path}/{$file}";
            if (!is_dir(dirname($target))) {
                mkdir(dirname($target), 0777, true);
            }
            file_put_contents($target, $json);
        }
        if ($files === []) {
            mkdir("{$this->project}/{$path}", 0777, true);
        }

        return new Package($name, 'bm-boards', '1.0.0', $path, []);
    }

    private function sdk(Package ...$packages): Sdk
    {
        $loader = new SdkLoader();
        return $loader->withBoardPackages($loader->load(self::FIXTURE), $packages, $this->project);
    }

    private const BOARD = '{"name":"Pack board","chip":"demo","flash":"8MB"}';

    public function test_sdk_boards_come_from_the_sdk(): void
    {
        $board = (new SdkLoader())->load(self::FIXTURE)->board('demo-board');
        self::assertSame(Board::SDK, $board->source);
        self::assertSame('sdk', $board->source);
        self::assertTrue($board->fromSdk());
        self::assertSame('sdk:demo-board', $board->qualifiedId());
    }

    public function test_loads_the_boards_of_a_package_for_the_sdk(): void
    {
        $sdk = $this->sdk($this->package('acme/boards', [
            'boards/demo/pack-board.json' => self::BOARD,
            'boards/other/foreign.json' => '{"name":"Foreign","chip":"ghost","flash":"4MB"}',
            'boards/demo/notes.txt' => 'not a board',
        ]));
        self::assertSame(['demo-board', 'pack-board'], array_keys($sdk->boards));
        $board = $sdk->board('pack-board');
        self::assertSame('Pack board', $board->name);
        self::assertSame('acme/boards', $board->source);
        self::assertFalse($board->fromSdk());
        self::assertSame('acme/boards:pack-board', $board->qualifiedId());
        self::assertSame('sdk', $sdk->board('demo-board')->source);
    }

    public function test_keeps_the_rest_of_the_sdk(): void
    {
        $loader = new SdkLoader();
        $base = $loader->load(self::FIXTURE);
        $sdk = $loader->withBoardPackages($base, [$this->package('acme/boards', ['boards/demo/pack-board.json' => self::BOARD])], $this->project);
        self::assertNotSame($base, $sdk);
        self::assertSame(['demo-board'], array_keys($base->boards));
        self::assertSame($base->dir, $sdk->dir);
        self::assertSame($base->descriptor, $sdk->descriptor);
        self::assertSame($base->chips, $sdk->chips);
        self::assertSame($base->runtimes, $sdk->runtimes);
        self::assertSame($base->storages, $sdk->storages);
        self::assertSame($base->phpExtensions, $sdk->phpExtensions);
    }

    public function test_other_package_types_and_folders_of_other_sdks_are_ignored(): void
    {
        $sdk = $this->sdk(
            $this->package('acme/only-other', ['boards/other/x.json' => self::BOARD]),
            $this->package('acme/empty', []),
            new Package('acme/ext-x', 'bm-ext', '1.0.0', 'missing', ['module' => 'x']),
        );
        self::assertSame(['demo-board'], array_keys($sdk->boards));
    }

    public function test_package_board_is_checked_like_an_sdk_board(): void
    {
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage("acme/boards: boards/demo/x.json: chip 'y' does not exist");
        $this->sdk($this->package('acme/boards', ['boards/demo/x.json' => '{"name":"X","chip":"y","flash":"4MB"}']));
    }

    public function test_package_board_block_unsupported_by_the_chip_is_rejected(): void
    {
        $this->expectExceptionMessage("acme/boards: boards/demo/x.json: chip 'demo' does not support network 'w5500'");
        $this->sdk($this->package('acme/boards', ['boards/demo/x.json' => '{"name":"X","chip":"demo","flash":"4MB","network":{"type":"w5500"}}']));
    }

    public function test_malformed_package_board_names_package_and_file(): void
    {
        $this->expectExceptionMessage("acme/boards: boards/demo/x.json: 'flash' must be a non-empty string");
        $this->sdk($this->package('acme/boards', ['boards/demo/x.json' => '{"name":"X","chip":"demo"}']));
    }

    public function test_missing_package_directory_is_rejected(): void
    {
        $this->expectExceptionMessage('acme/gone: package directory not found: ');
        $this->sdk(new Package('acme/gone', 'bm-boards', '1.0.0', '.phpbm/packages/acme/gone', []));
    }

    public function test_absolute_package_path_is_used_as_is(): void
    {
        $package = $this->package('acme/boards', ['boards/demo/pack-board.json' => self::BOARD]);
        $absolute = new Package($package->name, $package->type, $package->version, "{$this->project}/{$package->path}", []);
        $loader = new SdkLoader();
        $sdk = $loader->withBoardPackages($loader->load(self::FIXTURE), [$absolute], '/nowhere');
        self::assertSame('acme/boards', $sdk->board('pack-board')->source);
    }

    public function test_adding_the_same_package_again_replaces_its_boards(): void
    {
        $loader = new SdkLoader();
        $package = $this->package('acme/boards', ['boards/demo/pack-board.json' => self::BOARD]);
        $once = $loader->withBoardPackages($loader->load(self::FIXTURE), [$package], $this->project);
        $twice = $loader->withBoardPackages($once, [$package], $this->project);
        self::assertSame(['demo-board', 'pack-board'], array_keys($twice->boards));
        self::assertSame('Pack board', $twice->board('pack-board')->name);
    }

    public function test_id_in_the_sdk_and_a_package_needs_the_long_form(): void
    {
        $sdk = $this->sdk($this->package('acme/boards', ['boards/demo/demo-board.json' => self::BOARD]));
        self::assertSame(['sdk:demo-board', 'acme/boards:demo-board'], array_keys($sdk->boards));
        self::assertSame('Demo board', $sdk->board('sdk:demo-board')->name);
        self::assertSame('Pack board', $sdk->board('acme/boards:demo-board')->name);
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage("board 'demo-board' is defined by the sdk and acme/boards: use 'sdk:demo-board' or 'acme/boards:demo-board'");
        $sdk->board('demo-board');
    }

    public function test_id_in_two_packages_needs_the_long_form(): void
    {
        $sdk = $this->sdk(
            $this->package('acme/boards', ['boards/demo/twin.json' => self::BOARD]),
            $this->package('other/boards', ['boards/demo/twin.json' => '{"name":"Other twin","chip":"demo","flash":"4MB"}']),
        );
        self::assertSame(['demo-board', 'acme/boards:twin', 'other/boards:twin'], array_keys($sdk->boards));
        self::assertSame('Other twin', $sdk->board('other/boards:twin')->name);
        $this->expectExceptionMessage("board 'twin' is defined by acme/boards and other/boards: use 'acme/boards:twin' or 'other/boards:twin'");
        $sdk->board('twin');
    }

    public function test_long_form_picks_a_unique_board_too(): void
    {
        $sdk = $this->sdk($this->package('acme/boards', ['boards/demo/pack-board.json' => self::BOARD]));
        self::assertSame('Pack board', $sdk->board('acme/boards:pack-board')->name);
        self::assertSame('Demo board', $sdk->board('sdk:demo-board')->name);
    }

    public function test_long_form_with_the_wrong_source_is_not_found(): void
    {
        $sdk = $this->sdk($this->package('acme/boards', ['boards/demo/pack-board.json' => self::BOARD]));
        $this->expectExceptionMessage("board 'sdk:pack-board' not found in sdk demo 1.2.0 (available: demo-board, pack-board)");
        $sdk->board('sdk:pack-board');
    }

    public function test_overrides_keep_the_source(): void
    {
        $sdk = $this->sdk($this->package('acme/boards', ['boards/demo/pack-board.json' => self::BOARD]));
        self::assertSame('acme/boards', $sdk->board('pack-board')->withOverrides(['led' => ['type' => 'ws2812', 'pin' => 4]])->source);
    }
}
