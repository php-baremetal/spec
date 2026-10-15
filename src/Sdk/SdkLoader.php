<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Sdk;

use PhpBaremetal\Spec\Sdk\Descriptor\Board;
use PhpBaremetal\Spec\Sdk\Descriptor\Chip;
use PhpBaremetal\Spec\Sdk\Descriptor\Module;
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
        );
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
