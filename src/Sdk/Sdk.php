<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Sdk;

use PhpBaremetal\Spec\Sdk\Descriptor\Board;
use PhpBaremetal\Spec\Sdk\Descriptor\Chip;
use PhpBaremetal\Spec\Sdk\Descriptor\Module;
use PhpBaremetal\Spec\Sdk\Descriptor\PhpExtension;
use PhpBaremetal\Spec\Sdk\Descriptor\SdkDescriptor;
use PhpBaremetal\Spec\SpecException;

final readonly class Sdk
{
    /**
     * @param array<string,Chip>   $chips
     * @param array<string,Board>  $boards
     * @param array<string,Module> $runtimes
     * @param array<string,Module> $storages
     * @param array<string,array<string,PhpExtension>> $phpExtensions minor => name => extension
     */
    public function __construct(
        public string $dir,
        public SdkDescriptor $descriptor,
        public array $chips,
        public array $boards,
        public array $runtimes,
        public array $storages,
        public array $phpExtensions = [],
    ) {
    }

    public function chip(string $id): Chip
    {
        return $this->pick($this->chips, $id, 'chip');
    }

    public function board(string $id): Board
    {
        return $this->pick($this->boards, $id, 'board');
    }

    public function runtime(string $id): Module
    {
        return $this->pick($this->runtimes, $id, 'runtime');
    }

    public function storage(string $id): Module
    {
        return $this->pick($this->storages, $id, 'storage');
    }

    public function phpExtension(string $minor, string $name): ?PhpExtension
    {
        return $this->phpExtensions[$minor][$name] ?? null;
    }

    public function driver(Context $context): Driver
    {
        $descriptor = $this->descriptor;
        $where = "sdk {$descriptor->name} {$descriptor->version}";
        if ($descriptor->contract !== Driver::CONTRACT) {
            throw new SpecException("{$where}: contract {$descriptor->contract} is not supported (expected " . Driver::CONTRACT . ')');
        }
        foreach ($descriptor->autoload as $prefix => $dir) {
            $this->register($prefix, $this->dir . '/' . trim($dir, '/'));
        }
        $class = $descriptor->driverClass;
        if (!class_exists($class)) {
            throw new SpecException("{$where}: driver class {$class} not found");
        }
        $driver = new $class($context);
        if (!$driver instanceof Driver) {
            throw new SpecException("{$where}: {$class} does not implement " . Driver::class);
        }

        return $driver;
    }

    private function register(string $prefix, string $dir): void
    {
        static $registered = [];
        $base = rtrim(realpath($dir) ?: $dir, '/');
        $key = $prefix . '|' . $base;
        if (isset($registered[$key])) {
            return;
        }
        $registered[$key] = true;
        spl_autoload_register(static function (string $class) use ($prefix, $base): void {
            if (str_starts_with($class, $prefix)) {
                $file = $base . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                if (is_file($file)) {
                    require_once $file;
                }
            }
        });
    }

    /** @return list<string> */
    public function patches(string $minor): array
    {
        $files = glob("{$this->dir}/php/{$minor}/patches/*.patch") ?: [];
        sort($files);
        return $files;
    }

    public function compatDir(string $minor): string
    {
        return "{$this->dir}/php/{$minor}/compat";
    }

    public function patchesHash(string $minor): string
    {
        return self::hashFiles($this->patches($minor));
    }

    /** @return list<string> patches applied to the external source <name> (sources/<name>/patches) */
    public function sourcePatches(string $name): array
    {
        $files = glob("{$this->dir}/sources/{$name}/patches/*.patch") ?: [];
        sort($files);
        return $files;
    }

    public function sourcePatchesHash(string $name): string
    {
        return self::hashFiles($this->sourcePatches($name));
    }

    /** @param list<string> $files */
    private static function hashFiles(array $files): string
    {
        $context = hash_init('sha256');
        foreach ($files as $file) {
            hash_update($context, basename($file) . "\0" . hash_file('sha256', $file) . "\n");
        }
        return hash_final($context);
    }

    private function pick(array $items, string $id, string $what): object
    {
        return $items[$id] ?? throw new SpecException(sprintf(
            "%s '%s' not found in sdk %s %s (available: %s)",
            $what,
            $id,
            $this->descriptor->name,
            $this->descriptor->version,
            implode(', ', array_keys($items)) ?: 'none',
        ));
    }
}
