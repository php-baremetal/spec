<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Tests\Manifest;

use PhpBaremetal\Spec\Manifest\FontBake;
use PhpBaremetal\Spec\Manifest\Fonts;
use PhpBaremetal\Spec\Manifest\Project;
use PhpBaremetal\Spec\SpecException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FontsTest extends TestCase
{
    private const BAREMETAL = [
        'sdk' => 'esp32@^1.0',
        'board' => 'waveshare-esp32-s3-eth',
        'runtime' => 'event-driven',
        'storage' => 'embedded',
    ];

    private function project(array $fonts): Project
    {
        return Project::fromComposer('acme/blink', [...self::BAREMETAL, 'fonts' => $fonts], '8.4.25');
    }

    private function bake(array $entry): Project
    {
        return $this->project(['bake' => [['name' => 'title', 'source' => 'fonts-src/a.ttf', 'size' => 16, ...$entry]]]);
    }

    private function assertRejected(callable $build, string $message): void
    {
        try {
            $build();
            self::fail('expected a SpecException');
        } catch (SpecException $e) {
            self::assertSame($message, $e->getMessage());
        }
    }

    public function test_defaults_when_fonts_is_missing(): void
    {
        $project = Project::fromComposer('acme/blink', self::BAREMETAL, '8.4.25');
        self::assertTrue($project->fonts->default);
        self::assertSame('project-fonts', $project->fonts->dir);
        self::assertSame([], $project->fonts->bake);
        self::assertArrayNotHasKey('fonts', $project->toArray());
    }

    public function test_full_fonts_are_read(): void
    {
        $fonts = $this->project([
            'default' => false,
            'dir' => 'my-fonts',
            'bake' => [
                ['name' => 'title', 'source' => 'fonts-src/Roboto.ttf', 'size' => 28, 'bpp' => 2, 'chars' => 'Home °C%'],
                ['name' => 'icons', 'source' => 'fonts-src/weather.BDF', 'range' => '0x20-0x7E,0xA0-0xFF'],
            ],
        ])->fonts;
        self::assertFalse($fonts->default);
        self::assertSame('my-fonts', $fonts->dir);
        self::assertCount(2, $fonts->bake);
        self::assertSame('title', $fonts->bake[0]->name);
        self::assertSame('fonts-src/Roboto.ttf', $fonts->bake[0]->source);
        self::assertSame(28, $fonts->bake[0]->size);
        self::assertSame(2, $fonts->bake[0]->bpp);
        self::assertSame('Home °C%', $fonts->bake[0]->chars);
        self::assertNull($fonts->bake[0]->range);
        self::assertNull($fonts->bake[1]->size);
        self::assertSame(1, $fonts->bake[1]->bpp);
        self::assertSame('0x20-0x7E,0xA0-0xFF', $fonts->bake[1]->range);
    }

    public function test_default_must_be_boolean(): void
    {
        $this->assertRejected(
            fn () => $this->project(['default' => 'yes']),
            "extra.baremetal: 'fonts.default' must be true or false",
        );
    }

    #[DataProvider('badDirs')]
    public function test_dir_must_be_relative_inside_the_project(string $dir): void
    {
        $this->assertRejected(
            fn () => $this->project(['dir' => $dir]),
            "extra.baremetal: 'fonts.dir' must be a relative path inside the project",
        );
    }

    public static function badDirs(): iterable
    {
        yield 'absolute' => ['/etc/fonts'];
        yield 'backslash' => ['\\fonts'];
        yield 'drive' => ['C:\\fonts'];
        yield 'parent' => ['../fonts'];
        yield 'nested parent' => ['a/../../b'];
    }

    #[DataProvider('badNames')]
    public function test_bake_name_is_checked(array $entry): void
    {
        $this->assertRejected(
            fn () => $this->project(['bake' => [$entry]]),
            "extra.baremetal: 'fonts.bake[0].name' must match [a-z0-9][a-z0-9-]*",
        );
    }

    public static function badNames(): iterable
    {
        yield 'missing' => [['source' => 'a.ttf', 'size' => 8]];
        yield 'uppercase' => [['name' => 'Title', 'source' => 'a.ttf', 'size' => 8]];
        yield 'leading dash' => [['name' => '-t', 'source' => 'a.ttf', 'size' => 8]];
        yield 'newline' => [['name' => "t\n", 'source' => 'a.ttf', 'size' => 8]];
    }

    public function test_bake_source_must_be_ttf_or_bdf(): void
    {
        $this->assertRejected(
            fn () => $this->bake(['source' => 'fonts-src/a.otf']),
            "extra.baremetal: 'fonts.bake[0].source' must be a .ttf or .bdf file",
        );
    }

    public function test_bake_bpp_is_checked(): void
    {
        $this->assertRejected(
            fn () => $this->bake(['bpp' => 3]),
            "extra.baremetal: 'fonts.bake[0].bpp' must be 1, 2 or 4",
        );
    }

    #[DataProvider('badSizes')]
    public function test_bake_size_is_checked(mixed $size): void
    {
        $this->assertRejected(
            fn () => $this->bake(['size' => $size]),
            "extra.baremetal: 'fonts.bake[0].size' must be an integer between 4 and 256",
        );
    }

    public static function badSizes(): iterable
    {
        yield 'string' => ['16'];
        yield 'float' => [16.5];
        yield 'small' => [3];
        yield 'large' => [257];
    }

    public function test_chars_and_range_are_exclusive(): void
    {
        $this->assertRejected(
            fn () => $this->bake(['chars' => 'ab', 'range' => '0x20-0x7E']),
            "extra.baremetal: 'fonts.bake[0]' takes chars or range, not both",
        );
    }

    #[DataProvider('badRanges')]
    public function test_range_is_checked(string $range): void
    {
        $this->assertRejected(
            fn () => $this->bake(['range' => $range]),
            "extra.baremetal: 'fonts.bake[0].range' must look like 0x20-0x7E",
        );
    }

    public static function badRanges(): iterable
    {
        yield 'decimal' => ['32-126'];
        yield 'single' => ['0x20'];
        yield 'reversed' => ['0x7E-0x20'];
        yield 'second reversed' => ['0x20-0x7E,0xFF-0xA0'];
        yield 'trailing comma' => ['0x20-0x7E,'];
    }

    public function test_unknown_keys_are_rejected(): void
    {
        $this->assertRejected(
            fn () => $this->project(['x' => 1]),
            "extra.baremetal: unknown key 'x' in fonts (allowed: default, dir, bake)",
        );
        $this->assertRejected(
            fn () => $this->bake(['x' => 1]),
            "extra.baremetal: unknown key 'x' in fonts.bake[0] (allowed: name, source, size, bpp, chars, range)",
        );
    }

    public function test_round_trip(): void
    {
        $project = $this->project([
            'default' => false,
            'dir' => 'my-fonts',
            'bake' => [
                ['name' => 'title', 'source' => 'fonts-src/Roboto.ttf', 'size' => 28, 'bpp' => 2, 'chars' => 'Home'],
                ['name' => 'icons', 'source' => 'fonts-src/weather.bdf'],
            ],
        ]);
        $again = Project::fromArray(json_decode(json_encode($project->toArray()), true), 'manifest.project');
        self::assertEquals($project, $again);
        self::assertInstanceOf(Fonts::class, $again->fonts);
        self::assertInstanceOf(FontBake::class, $again->fonts->bake[1]);
    }
}
