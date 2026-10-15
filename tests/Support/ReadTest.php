<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Tests\Support;

use PhpBaremetal\Spec\SpecException;
use PhpBaremetal\Spec\Support\Json;
use PhpBaremetal\Spec\Support\Read;
use PHPUnit\Framework\TestCase;

final class ReadTest extends TestCase
{
    public function test_string_returns_value(): void
    {
        self::assertSame('x', Read::string(['a' => 'x'], 'a', 'f.json'));
    }

    public function test_string_rejects_missing_with_location(): void
    {
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage("f.json: 'a' must be a non-empty string");
        Read::string([], 'a', 'f.json');
    }

    public function test_optional_string_is_null_when_absent(): void
    {
        self::assertNull(Read::optionalString([], 'a', 'f.json'));
    }

    public function test_string_list_rejects_non_strings(): void
    {
        $this->expectException(SpecException::class);
        Read::stringList(['a' => ['x', 3]], 'a', 'f.json');
    }

    public function test_map_rejects_list(): void
    {
        $this->expectException(SpecException::class);
        Read::map(['a' => [1, 2]], 'a', 'f.json');
    }

    public function test_map_defaults_to_empty(): void
    {
        self::assertSame([], Read::map([], 'a', 'f.json'));
    }

    public function test_sha256_validates_format(): void
    {
        $this->expectException(SpecException::class);
        Read::sha256(['s' => 'abc'], 's', 'f.json');
    }

    public function test_json_decode_file_reports_invalid_json(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'spec');
        file_put_contents($file, '{nope');
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage('invalid JSON');
        Json::decodeFile($file);
    }

    public function test_write_atomic_replaces_content(): void
    {
        $dir = sys_get_temp_dir() . '/spec-' . bin2hex(random_bytes(4));
        Json::writeAtomic($dir . '/a/b.json', "{}\n");
        self::assertSame("{}\n", file_get_contents($dir . '/a/b.json'));
    }
}
