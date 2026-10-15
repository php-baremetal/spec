<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Manifest;

use PhpBaremetal\Spec\SpecException;
use PhpBaremetal\Spec\Support\Json;

final readonly class Manifest
{
    public const SCHEMA = 1;
    public const PATH = '.phpbm/manifest.json';

    /** @param list<Package> $packages */
    public function __construct(public Project $project, public array $packages)
    {
    }

    public static function fromArray(array $data): self
    {
        $schema = $data['schema'] ?? null;
        if ($schema !== self::SCHEMA) {
            throw new SpecException(sprintf('manifest: unsupported schema %s, expected %d', json_encode($schema), self::SCHEMA));
        }
        $project = $data['project'] ?? null;
        if (!is_array($project)) {
            throw new SpecException("manifest: 'project' must be an object");
        }
        $packages = $data['packages'] ?? [];
        if (!is_array($packages) || !array_is_list($packages)) {
            throw new SpecException("manifest: 'packages' must be a list");
        }

        $parsed = [];
        foreach ($packages as $i => $package) {
            $parsed[] = Package::fromArray(is_array($package) ? $package : [], "manifest.packages[{$i}]");
        }

        return new self(Project::fromArray($project, 'manifest.project'), $parsed);
    }

    public static function fromFile(string $path): self
    {
        return self::fromArray(Json::decodeFile($path));
    }

    public function toArray(): array
    {
        return [
            'schema' => self::SCHEMA,
            'generated-by' => 'php-baremetal/conductor',
            'project' => $this->project->toArray(),
            'packages' => array_map(static fn (Package $p) => $p->toArray(), $this->packages),
        ];
    }

    public function toJson(): string
    {
        return Json::encode($this->toArray());
    }

    /** @return list<Package> */
    public function ofKind(string $kind): array
    {
        return array_values(array_filter($this->packages, static fn (Package $p) => $p->kind() === $kind));
    }

    public function has(string $packageName): bool
    {
        foreach ($this->packages as $package) {
            if ($package->name === $packageName) {
                return true;
            }
        }
        return false;
    }
}
