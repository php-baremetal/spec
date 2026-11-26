<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Sdk\Descriptor;

use PhpBaremetal\Spec\SpecException;
use PhpBaremetal\Spec\Support\Read;

final readonly class SdkDescriptor
{
    /** Composer's package name format. */
    private const PACKAGE_NAME = '#^[a-z0-9]([_.-]?[a-z0-9]+)*/[a-z0-9](([_.]|-{1,2})?[a-z0-9]+)*$#D';

    /**
     * @param array<string,string>        $autoload namespace prefix => directory relative to the SDK
     * @param array<string,PhpRelease>    $php      minor => release
     * @param array<string,SourceArchive> $sources
     * @param array<string,Option>        $options
     * @param list<string>                $boardPackages official bm-boards packages (Composer names)
     */
    public function __construct(
        public string $name,
        public string $version,
        public int $contract,
        public string $driverClass,
        public array $autoload,
        public array $php,
        public array $sources,
        public array $options = [],
        public array $boardPackages = [],
    ) {
    }

    public static function fromArray(array $data, string $where = 'sdk.json'): self
    {
        $driver = Read::map($data, 'driver', $where);
        $autoload = Read::map(Read::map($driver, 'autoload', "{$where} driver"), 'psr-4', "{$where} driver.autoload");
        if ($autoload === []) {
            throw new SpecException("{$where}: driver.autoload.psr-4 must map at least one namespace");
        }
        foreach ($autoload as $prefix => $dir) {
            if (!is_string($dir) || $dir === '') {
                throw new SpecException("{$where}: driver.autoload.psr-4 '{$prefix}' must map to a directory");
            }
        }

        $php = [];
        foreach (Read::map($data, 'php', $where) as $minor => $release) {
            $php[(string) $minor] = PhpRelease::fromArray((string) $minor, is_array($release) ? $release : [], "{$where} php.{$minor}");
        }
        if ($php === []) {
            throw new SpecException("{$where}: 'php' must declare at least one PHP release");
        }

        $sources = [];
        foreach (Read::map($data, 'sources', $where) as $name => $source) {
            $sources[(string) $name] = SourceArchive::fromArray((string) $name, is_array($source) ? $source : [], "{$where} sources.{$name}");
        }

        $options = [];
        $declared = array_key_exists('options', $data) ? Read::map($data, 'options', $where) : [];
        foreach ($declared as $name => $option) {
            $options[(string) $name] = Option::fromArray((string) $name, is_array($option) ? $option : [], "{$where} option {$name}");
        }

        $boardPackages = $data['board-packages'] ?? [];
        $valid = is_array($boardPackages) && array_is_list($boardPackages);
        foreach ($valid ? $boardPackages : [] as $package) {
            if (!is_string($package) || !preg_match(self::PACKAGE_NAME, $package)) {
                $valid = false;
            }
        }
        if (!$valid) {
            throw new SpecException("{$where}: 'board-packages' must be a list of Composer package names (vendor/name)");
        }

        return new self(
            Read::string($data, 'name', $where),
            Read::string($data, 'version', $where),
            Read::int($data, 'contract', $where),
            Read::string($driver, 'class', "{$where} driver"),
            $autoload,
            $php,
            $sources,
            $options,
            $boardPackages,
        );
    }

    public function release(string $minor): PhpRelease
    {
        return $this->php[$minor] ?? throw new SpecException(sprintf(
            'sdk %s %s does not support PHP %s (supported: %s)',
            $this->name,
            $this->version,
            $minor,
            implode(', ', array_keys($this->php)),
        ));
    }
}
