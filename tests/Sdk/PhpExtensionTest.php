<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Tests\Sdk;

use PhpBaremetal\Spec\Sdk\Descriptor\PhpExtension;
use PhpBaremetal\Spec\SpecException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhpExtensionTest extends TestCase
{
    private static function demo(): PhpExtension
    {
        $file = __DIR__ . '/../fixtures/sdk/php/8.4/extensions/demo.json';
        return PhpExtension::fromArray('8.4', 'demo', json_decode((string) file_get_contents($file), true), 'demo.json');
    }

    public function test_parses_a_descriptor(): void
    {
        $demo = self::demo();
        self::assertSame('demo', $demo->module);
        self::assertSame('demo_module_entry', $demo->entry);
        self::assertSame(['ext/demo/*.c', 'sqlite:sqlite3.c'], $demo->sources);
        self::assertSame(['ext/demo/big.c'], $demo->exclude);
        self::assertSame(['ext/demo', 'sqlite:.'], $demo->includeDirs);
        self::assertSame(['HAVE_DEMO' => 1], $demo->defines);
        self::assertSame(['base'], $demo->requiresExtensions);
        self::assertSame(['sqlite'], $demo->requiresSources);
        self::assertSame(['demo-big', 'demo-mode'], array_keys($demo->options));
        self::assertFalse($demo->options['demo-big']->default);
        self::assertSame('about 10 KB', $demo->cost);
        self::assertSame(12, $demo->startupKb);
    }

    public function test_resolves_the_build_from_the_option_values(): void
    {
        $demo = self::demo();
        self::assertSame(
            ['layers' => [['sources' => ['ext/demo/*.c', 'sqlite:sqlite3.c'], 'exclude' => ['ext/demo/big.c']]], 'includeDirs' => ['ext/demo', 'sqlite:.'], 'defines' => ['HAVE_DEMO' => 1], 'startupKb' => 12],
            $demo->resolve([]),
        );
        self::assertSame(
            [
                'layers' => [
                    ['sources' => ['ext/demo/*.c', 'sqlite:sqlite3.c'], 'exclude' => ['ext/demo/big.c']],
                    ['sources' => ['ext/demo/big.c'], 'exclude' => []],
                    ['sources' => [], 'exclude' => ['ext/demo/fast.c']],
                ],
                'includeDirs' => ['ext/demo', 'sqlite:.'],
                'defines' => ['HAVE_DEMO' => 1, 'DEMO_BIG' => 1, 'DEMO_SMALL' => 'yes'],
                'startupKb' => 16,
            ],
            $demo->resolve(['demo-big' => true, 'demo-mode' => 'small']),
        );
    }

    public function test_a_when_can_add_include_dirs_and_only_used_sources_are_needed(): void
    {
        $x = PhpExtension::fromArray('8.4', 'x', [
            'module' => 'x',
            'sources' => ['ext/x/*.c'],
            'include-dirs' => ['ext/x'],
            'requires' => ['sources' => ['onig', 'zlib']],
            'options' => ['x-regex' => [
                'description' => 'regex', 'type' => 'bool', 'default' => false,
                'when' => ['true' => ['sources' => ['onig:src/*.c'], 'include-dirs' => ['onig:src']]],
            ]],
        ], 'x.json');
        self::assertSame(['ext/x'], $x->resolve([])['includeDirs']);
        self::assertSame([], $x->usedSources([]));
        self::assertSame(['ext/x', 'onig:src'], $x->resolve(['x-regex' => true])['includeDirs']);
        self::assertSame(['onig'], $x->usedSources(['x-regex' => true]));
        self::assertSame(['sqlite'], self::demo()->usedSources([]));
    }

    public function test_splits_external_source_paths(): void
    {
        self::assertSame(['', 'ext/x/a.c'], PhpExtension::split('ext/x/a.c'));
        self::assertSame(['sqlite', 'src/*.c'], PhpExtension::split('sqlite:src/*.c'));
    }

    /** @return iterable<string,array{array<string,mixed>,string}> */
    public static function invalid(): iterable
    {
        $base = ['module' => 'x', 'sources' => ['ext/x/*.c']];
        yield 'unknown key' => [[...$base, 'src' => 1], "unknown key 'src'"];
        yield 'empty module' => [[...$base, 'module' => ''], "'module' must be a non-empty string"];
        yield 'module symbol' => [[...$base, 'module' => 'X_ext'], "'module' must be the PHP module name ([a-z_][a-z0-9_]*), got 'X_ext'"];
        yield 'module null' => [[...$base, 'module' => null], "'module' must be a non-empty string"];
        yield 'entry macro' => [[...$base, 'entry' => 'phpext_x_ptr()'], "'entry' must be the C symbol of the zend_module_entry"];
        yield 'entry empty' => [[...$base, 'entry' => ''], "'entry' must be a non-empty string"];
        yield 'no sources' => [[...$base, 'sources' => []], "'sources' must list at least one file pattern (omit it when all the code is in compat/extensions/x)"];
        yield 'idf requires name' => [[...$base, 'idf-requires' => ['mbed tls']], "invalid ESP-IDF component name 'mbed tls' in 'idf-requires'"];
        yield 'idf requires list' => [[...$base, 'idf-requires' => 'mbedtls'], "'idf-requires' must be a list of strings"];
        yield 'idf dependency name' => [[...$base, 'idf-dependencies' => ['zlib' => '==1.3.2']], "'idf-dependencies' key 'zlib' must be a registry component <namespace>/<name>"];
        yield 'idf dependency range' => [[...$base, 'idf-dependencies' => ['espressif/zlib' => '^1.3.2']], "'idf-dependencies' must pin 'espressif/zlib' to an exact version"];
        yield 'idf dependency bare' => [[...$base, 'idf-dependencies' => ['espressif/zlib' => '1.3.2']], "'idf-dependencies' must pin 'espressif/zlib' to an exact version"];
        yield 'dotdot' => [[...$base, 'sources' => ['../x.c']], "'sources' must hold relative paths without '..'"];
        yield 'absolute' => [[...$base, 'include-dirs' => ['/usr/include']], "'include-dirs' must hold relative paths"];
        yield 'semicolon' => [[...$base, 'sources' => ['ext/x/a;b.c']], "'sources' must not contain ';', '=' or '\"'"];
        yield 'equals' => [[...$base, 'include-dirs' => ['ext/x=y']], "'include-dirs' must not contain ';', '=' or '\"'"];
        yield 'quote' => [[...$base, 'exclude' => ['ext/x/"a.c']], "'exclude' must not contain ';', '=' or '\"'"];
        yield 'include dir wildcard' => [[...$base, 'include-dirs' => ['ext/x*']], "'include-dirs' cannot use wildcards: 'ext/x*'"];
        yield 'deep wildcard' => [[...$base, 'sources' => ['ext/*/a.c']], "may use only '*' and '?' wildcards, in the last segment"];
        yield 'undeclared source' => [[...$base, 'sources' => ['onig:src/*.c']], "'sources' uses the source 'onig', which is not in requires.sources"];
        yield 'when include dir source' => [[...$base, 'options' => ['x-b' => ['description' => 'b', 'type' => 'bool', 'default' => true, 'when' => ['true' => ['include-dirs' => ['onig:src']]]]]], "'include-dirs' uses the source 'onig', which is not in requires.sources"];
        yield 'requires key' => [[...$base, 'requires' => ['packages' => []]], "unknown key 'packages' in 'requires'"];
        yield 'self requirement' => [[...$base, 'requires' => ['extensions' => ['x']]], "invalid required extension 'x'"];
        yield 'bad define' => [[...$base, 'defines' => ['A B' => 1]], "invalid define name 'A B'"];
        yield 'when on int' => [[...$base, 'options' => ['x-n' => ['description' => 'n', 'type' => 'int', 'default' => 1, 'when' => ['1' => []]]]], "'when' is only allowed on enum and bool options"];
        yield 'when value' => [[...$base, 'options' => ['x-b' => ['description' => 'b', 'type' => 'bool', 'default' => true, 'when' => ['yes' => []]]]], "'when' value 'yes' must be one of true, false"];
        yield 'startup-kb type' => [[...$base, 'startup-kb' => '12'], "'startup-kb' must be an integer"];
        yield 'startup-kb negative' => [[...$base, 'startup-kb' => -1], "'startup-kb' must be >= 0"];
        yield 'when startup-kb negative' => [[...$base, 'options' => ['x-b' => ['description' => 'b', 'type' => 'bool', 'default' => true, 'when' => ['true' => ['startup-kb' => -2]]]]], "when.true: 'startup-kb' must be >= 0"];
        yield 'when key' => [[...$base, 'options' => ['x-b' => ['description' => 'b', 'type' => 'bool', 'default' => true, 'when' => ['true' => ['idf-requires' => []]]]]], "unknown key 'idf-requires' in 'when.true'"];
    }

    #[DataProvider('invalid')]
    public function test_rejects_invalid_descriptors(array $data, string $message): void
    {
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage($message);
        PhpExtension::fromArray('8.4', 'x', $data, 'x.json');
    }

    public function test_reads_the_idf_requirements(): void
    {
        $x = PhpExtension::fromArray('8.4', 'x', [
            'module' => 'x',
            'idf-requires' => ['mbedtls', 'espressif__zlib', 'mbedtls'],
            'idf-dependencies' => ['espressif/zlib' => '==1.3.2~1', 'espressif/a' => '==1.0.0'],
        ], 'x.json');
        self::assertSame([], $x->sources);
        self::assertSame(['mbedtls', 'espressif__zlib'], $x->idfRequires);
        self::assertSame(['espressif/a' => '==1.0.0', 'espressif/zlib' => '==1.3.2~1'], $x->idfDependencies);
        self::assertSame([], self::demo()->idfRequires);
        self::assertSame([], self::demo()->idfDependencies);
    }

    public function test_a_self_registering_extension_has_a_null_entry(): void
    {
        $opcache = PhpExtension::fromArray('8.4', 'opcache', ['module' => 'opcache', 'entry' => null, 'sources' => ['ext/opcache/*.c']], 'opcache.json');
        self::assertSame('opcache', $opcache->module);
        self::assertNull($opcache->entry);
    }

    public function test_entry_overrides_the_default_symbol(): void
    {
        $zlib = PhpExtension::fromArray('8.4', 'zlib', ['module' => 'zlib', 'entry' => 'php_zlib_module_entry', 'sources' => ['a.c']], 'zlib.json');
        self::assertSame('zlib', $zlib->module);
        self::assertSame('php_zlib_module_entry', $zlib->entry);
    }

    public function test_requires_the_module_key(): void
    {
        $this->expectExceptionMessage("'module' is required (the PHP module name)");
        PhpExtension::fromArray('8.4', 'x', ['sources' => ['ext/x/*.c']], 'x.json');
    }

    public function test_rejects_a_builtin_name(): void
    {
        $this->expectExceptionMessage("json.json: 'json' is a built-in PHP extension");
        PhpExtension::fromArray('8.4', 'json', ['module' => 'json2', 'sources' => ['a.c']], 'json.json');
    }

    public function test_builtin_modules_depend_on_the_minor(): void
    {
        $this->assertNotContains('uri', PhpExtension::builtin('8.4'));
        $this->assertContains('uri', PhpExtension::builtin('8.5'));
        $this->assertContains('uri', PhpExtension::builtin());
        $this->assertNotContains('date', PhpExtension::builtin());
        $this->assertSame(PhpExtension::builtin('8.3'), PhpExtension::BUILTIN);
    }

    public function test_rejects_an_invalid_name(): void
    {
        $this->expectExceptionMessage("extension name 'X-1' must match");
        PhpExtension::fromArray('8.4', 'X-1', ['module' => 'x', 'sources' => ['a.c']], 'X-1.json');
    }
}
