<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Sdk;

use PhpBaremetal\Spec\Sdk\Descriptor\Board;
use PhpBaremetal\Spec\Sdk\Descriptor\Chip;
use PhpBaremetal\Spec\Sdk\Descriptor\Module;
use PhpBaremetal\Spec\Sdk\Descriptor\PhpExtension;
use PhpBaremetal\Spec\Sdk\Descriptor\SdkDescriptor;
use PhpBaremetal\Spec\SpecException;
use PhpBaremetal\Spec\Support\Json;

final class SdkLoader
{
    public function load(string $dir): Sdk
    {
        $root = realpath($dir);
        if ($root === false || !is_dir($root)) {
            throw new SpecException("sdk directory not found: {$dir}");
        }

        $descriptor = SdkDescriptor::fromArray(Json::decodeFile("{$root}/sdk.json"));
        foreach (array_keys($descriptor->php) as $minor) {
            if (!is_dir("{$root}/php/{$minor}")) {
                throw new SpecException("sdk.json: PHP {$minor} is declared but php/{$minor}/ does not exist");
            }
        }

        $chips = [];
        foreach ($this->files($root, 'chips/*/chip.json') as $relative => $file) {
            $id = basename(dirname($file));
            $chips[$id] = Chip::fromArray($id, Json::decodeFile($file), $relative);
        }

        $boards = [];
        foreach ($this->files($root, 'boards/*.json') as $relative => $file) {
            $id = basename($file, '.json');
            $board = Board::fromArray($id, Json::decodeFile($file), $relative);
            $this->checkBoard($board, $chips, $relative);
            $boards[$id] = $board;
        }

        return new Sdk(
            $root,
            $descriptor,
            $chips,
            $boards,
            $this->modules($root, 'runtime'),
            $this->modules($root, 'storage'),
            $this->phpExtensions($root, $descriptor),
        );
    }

    /** @return array<string,array<string,PhpExtension>> */
    private function phpExtensions(string $root, SdkDescriptor $descriptor): array
    {
        $all = [];
        foreach (array_keys($descriptor->php) as $minor) {
            $extensions = [];
            // Module names (extension_loaded()) and zend_module_entry symbols are unique within a minor, built-ins included.
            $modules = [];
            $entries = [];
            foreach (PhpExtension::builtinEntries((string) $minor) as $builtin => $symbol) {
                $modules[$builtin] = "the built-in extension {$builtin}";
                $entries[$symbol] = "the built-in extension {$builtin}";
            }
            foreach ($this->files($root, "php/{$minor}/extensions/*.json") as $relative => $file) {
                $extension = PhpExtension::fromArray((string) $minor, basename($file, '.json'), Json::decodeFile($file), $relative);
                if (isset($modules[$extension->module])) {
                    throw new SpecException("{$relative}: module '{$extension->module}' is already registered by {$modules[$extension->module]}");
                }
                $modules[$extension->module] = "the extension {$extension->name}";
                if ($extension->entry !== null) {
                    if (isset($entries[$extension->entry])) {
                        throw new SpecException("{$relative}: entry '{$extension->entry}' is already defined by {$entries[$extension->entry]}");
                    }
                    $entries[$extension->entry] = "the extension {$extension->name}";
                }
                foreach ($extension->requiresSources as $source) {
                    if (!isset($descriptor->sources[$source])) {
                        throw new SpecException("{$relative}: requires the source '{$source}', which sdk.json does not declare");
                    }
                }
                $extensions[$extension->name] = $extension;
            }
            foreach ($extensions as $extension) {
                foreach ($extension->requiresExtensions as $required) {
                    if (!isset($extensions[$required])) {
                        throw new SpecException("php/{$minor}/extensions/{$extension->name}.json: requires the extension '{$required}', which PHP {$minor} does not provide");
                    }
                }
                $this->checkCycle($extensions, $extension->name, []);
            }
            if ($extensions !== []) {
                $all[(string) $minor] = $extensions;
            }
        }
        return $all;
    }

    /**
     * @param array<string,PhpExtension> $extensions
     * @param list<string>               $path
     */
    private function checkCycle(array $extensions, string $name, array $path): void
    {
        if (in_array($name, $path, true)) {
            throw new SpecException("php/{$extensions[$name]->minor}/extensions: the extensions require each other (" . implode(' -> ', [...$path, $name]) . ')');
        }
        foreach ($extensions[$name]->requiresExtensions as $required) {
            $this->checkCycle($extensions, $required, [...$path, $name]);
        }
    }

    /** @param array<string,Chip> $chips */
    private function checkBoard(Board $board, array $chips, string $where): void
    {
        $chip = $chips[$board->chip] ?? throw new SpecException("{$where}: chip '{$board->chip}' does not exist");
        if ($board->psram !== null && !in_array($board->psram, $chip->psram, true)) {
            throw new SpecException("{$where}: chip '{$chip->id}' does not support psram '{$board->psram}'");
        }
        foreach ($board->capabilities() as $capability => $type) {
            if (!$chip->supports($capability, $type)) {
                throw new SpecException("{$where}: chip '{$chip->id}' does not support {$capability} '{$type}'");
            }
        }
    }

    /** @return array<string,Module> */
    private function modules(string $root, string $kind): array
    {
        $modules = [];
        foreach ($this->files($root, "{$kind}s/*/{$kind}.json") as $relative => $file) {
            $id = basename(dirname($file));
            $modules[$id] = Module::fromArray($kind, $id, Json::decodeFile($file), $relative);
        }
        return $modules;
    }

    /** @return array<string,string> relative path => absolute path */
    private function files(string $root, string $pattern): array
    {
        $files = [];
        foreach (glob("{$root}/{$pattern}") ?: [] as $file) {
            $files[substr($file, strlen($root) + 1)] = $file;
        }
        ksort($files);
        return $files;
    }
}
