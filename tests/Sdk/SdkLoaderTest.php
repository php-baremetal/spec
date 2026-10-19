<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Tests\Sdk;

use PhpBaremetal\Spec\Sdk\Descriptor\PhpExtension;
use PhpBaremetal\Spec\Sdk\SdkLoader;
use PhpBaremetal\Spec\SpecException;
use PHPUnit\Framework\TestCase;

final class SdkLoaderTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../fixtures/sdk';

    private function copyFixture(): string
    {
        $dir = sys_get_temp_dir() . '/spec-sdk-' . bin2hex(random_bytes(4));
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::FIXTURE, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        mkdir($dir);
        foreach ($it as $file) {
            $target = $dir . '/' . $it->getSubPathname();
            $file->isDir() ? mkdir($target) : copy((string) $file, $target);
        }
        return $dir;
    }

    public function test_loads_every_descriptor(): void
    {
        $sdk = (new SdkLoader())->load(self::FIXTURE);
        self::assertSame('demo', $sdk->descriptor->name);
        self::assertSame('demo', $sdk->board('demo-board')->chip);
        self::assertSame('demo', $sdk->chip('demo')->target);
        self::assertSame('runtime', $sdk->runtime('loop')->kind);
        self::assertSame('storage', $sdk->storage('flash')->kind);
    }

    public function test_sdk_declares_options(): void
    {
        $options = (new SdkLoader())->load(self::FIXTURE)->descriptor->options;
        self::assertSame(['network', 'tag'], array_keys($options));
        self::assertSame('off', $options['network']->default);
        self::assertSame('string', $options['tag']->type);
    }

    public function test_modules_declare_options(): void
    {
        $options = (new SdkLoader())->load(self::FIXTURE)->runtime('loop')->options;
        self::assertSame(['on-error', 'restart-delay'], array_keys($options));
        self::assertSame('enum', $options['on-error']->type);
        self::assertSame('stop', $options['on-error']->default);
        self::assertSame(5, $options['restart-delay']->default);
        self::assertSame([], (new SdkLoader())->load(self::FIXTURE)->storage('flash')->options);
    }

    public function test_patches_are_sorted_and_hashed(): void
    {
        $sdk = (new SdkLoader())->load(self::FIXTURE);
        self::assertSame(['0001-first.patch', '0002-second.patch'], array_map('basename', $sdk->patches('8.4')));
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $sdk->patchesHash('8.4'));
        self::assertSame(realpath(self::FIXTURE) . '/php/8.4/compat', $sdk->compatDir('8.4'));
    }

    public function test_unknown_board_lists_available_ones(): void
    {
        $sdk = (new SdkLoader())->load(self::FIXTURE);
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage("board 'nope' not found in sdk demo 1.2.0 (available: demo-board)");
        $sdk->board('nope');
    }

    public function test_board_with_unknown_chip_is_rejected(): void
    {
        $dir = $this->copyFixture();
        file_put_contents($dir . '/boards/bad.json', '{"name":"Bad","chip":"ghost","flash":"4MB"}');
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage("boards/bad.json: chip 'ghost' does not exist");
        (new SdkLoader())->load($dir);
    }

    public function test_board_peripheral_unsupported_by_chip_is_rejected(): void
    {
        $dir = $this->copyFixture();
        file_put_contents($dir . '/boards/bad.json', '{"name":"Bad","chip":"demo","flash":"4MB","network":{"type":"w5500"}}');
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage("boards/bad.json: chip 'demo' does not support network 'w5500'");
        (new SdkLoader())->load($dir);
    }

    public function test_missing_php_directory_is_rejected(): void
    {
        $dir = $this->copyFixture();
        $json = json_decode(file_get_contents($dir . '/sdk.json'), true);
        $json['php']['8.5'] = ['version' => '8.5.10', 'url' => 'u', 'sha256' => str_repeat('b', 64)];
        file_put_contents($dir . '/sdk.json', json_encode($json));
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage("sdk.json: PHP 8.5 is declared but php/8.5/ does not exist");
        (new SdkLoader())->load($dir);
    }

    public function test_loads_the_php_extensions_of_each_minor(): void
    {
        $sdk = (new SdkLoader())->load(self::FIXTURE);
        self::assertSame(['base', 'demo'], array_keys($sdk->phpExtensions['8.4']));
        self::assertSame('demo', $sdk->phpExtension('8.4', 'demo')?->module);
        self::assertSame('demo_module_entry', $sdk->phpExtension('8.4', 'demo')?->entry);
        self::assertNull($sdk->phpExtension('8.4', 'nope'));
        self::assertNull($sdk->phpExtension('8.3', 'demo'));
    }

    public function test_source_patches_are_sorted_and_hashed(): void
    {
        $sdk = (new SdkLoader())->load(self::FIXTURE);
        self::assertSame(['0001-y.patch'], array_map('basename', $sdk->sourcePatches('sqlite')));
        self::assertSame([], $sdk->sourcePatches('none'));
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $sdk->sourcePatchesHash('sqlite'));
        self::assertNotSame($sdk->sourcePatchesHash('sqlite'), $sdk->sourcePatchesHash('none'));
    }

    public function test_extension_with_an_undeclared_source_is_rejected(): void
    {
        $dir = $this->copyFixture();
        file_put_contents($dir . '/php/8.4/extensions/onig.json', '{"module":"o","sources":["onig:a.c"],"requires":{"sources":["onig"]}}');
        $this->expectExceptionMessage("php/8.4/extensions/onig.json: requires the source 'onig', which sdk.json does not declare");
        (new SdkLoader())->load($dir);
    }

    public function test_extension_requiring_a_missing_extension_is_rejected(): void
    {
        $dir = $this->copyFixture();
        file_put_contents($dir . '/php/8.4/extensions/pdo_x.json', '{"module":"m","sources":["a.c"],"requires":{"extensions":["pdo"]}}');
        $this->expectExceptionMessage("php/8.4/extensions/pdo_x.json: requires the extension 'pdo', which PHP 8.4 does not provide");
        (new SdkLoader())->load($dir);
    }

    public function test_extensions_requiring_each_other_are_rejected(): void
    {
        $dir = $this->copyFixture();
        file_put_contents($dir . '/php/8.4/extensions/base.json', '{"module":"m","sources":["a.c"],"requires":{"extensions":["demo"]}}');
        $this->expectExceptionMessage('the extensions require each other (base -> demo -> base)');
        (new SdkLoader())->load($dir);
    }

    public function test_two_extensions_with_the_same_module_are_rejected(): void
    {
        $dir = $this->copyFixture();
        file_put_contents($dir . '/php/8.4/extensions/other.json', '{"module":"demo","entry":"other_module_entry","sources":["a.c"]}');
        $this->expectExceptionMessage("php/8.4/extensions/other.json: module 'demo' is already registered by the extension demo");
        (new SdkLoader())->load($dir);
    }

    public function test_two_extensions_with_the_same_entry_are_rejected(): void
    {
        $dir = $this->copyFixture();
        file_put_contents($dir . '/php/8.4/extensions/other.json', '{"module":"other","entry":"demo_module_entry","sources":["a.c"]}');
        $this->expectExceptionMessage("php/8.4/extensions/other.json: entry 'demo_module_entry' is already defined by the extension demo");
        (new SdkLoader())->load($dir);
    }

    public function test_an_extension_with_a_builtin_entry_is_rejected(): void
    {
        $dir = $this->copyFixture();
        file_put_contents($dir . '/php/8.4/extensions/js.json', '{"module":"js","entry":"json_module_entry","sources":["a.c"]}');
        $this->expectExceptionMessage("php/8.4/extensions/js.json: entry 'json_module_entry' is already defined by the built-in extension json");
        (new SdkLoader())->load($dir);
    }

    public function test_an_extension_with_a_builtin_module_is_rejected(): void
    {
        $dir = $this->copyFixture();
        file_put_contents($dir . '/php/8.4/extensions/js.json', '{"module":"json","sources":["a.c"]}');
        $this->expectExceptionMessage("php/8.4/extensions/js.json: module 'json' is already registered by the built-in extension json");
        (new SdkLoader())->load($dir);
    }

    public function test_an_extension_with_the_real_symbol_of_a_builtin_is_rejected(): void
    {
        $dir = $this->copyFixture();
        file_put_contents($dir . '/php/8.4/extensions/js.json', '{"module":"js","entry":"basic_functions_module","sources":["a.c"]}');
        $this->expectExceptionMessage("php/8.4/extensions/js.json: entry 'basic_functions_module' is already defined by the built-in extension standard");
        (new SdkLoader())->load($dir);
    }

    public function test_builtin_entries_are_the_real_symbols(): void
    {
        self::assertSame('zend_builtin_module', PhpExtension::builtinEntries('8.4')['core']);
        self::assertArrayNotHasKey('uri', PhpExtension::builtinEntries('8.4'));
        self::assertSame('uri_module_entry', PhpExtension::builtinEntries('8.5')['uri']);
    }
}
