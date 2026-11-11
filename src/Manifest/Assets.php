<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Manifest;

use PhpBaremetal\Spec\SpecException;

final readonly class Assets
{
    public function __construct(
        /** @var list<AssetDir> */
        public array $dirs = [],
    ) {
    }

    public static function fromArray(array $data, string $where): self
    {
        $list = $data['assets'] ?? [];
        if (!is_array($list) || !array_is_list($list)) {
            throw new SpecException("{$where}: 'assets' must be a list");
        }

        $dirs = [];
        foreach ($list as $i => $entry) {
            if (!is_array($entry) || $entry === [] || array_is_list($entry)) {
                throw new SpecException("{$where}: 'assets[{$i}]' must be an object");
            }
            $dirs[] = AssetDir::fromArray($entry, $where, "assets[{$i}]");
        }

        foreach ($dirs as $j => $b) {
            for ($i = 0; $i < $j; $i++) {
                $a = $dirs[$i];
                if ($a->dir === $b->dir) {
                    throw new SpecException("{$where}: 'assets[{$j}].dir' ('{$b->dir}') is the same as 'assets[{$i}].dir' ('{$a->dir}')");
                }
                if (str_starts_with($b->dir, $a->dir . '/')) {
                    throw new SpecException("{$where}: 'assets[{$j}].dir' ('{$b->dir}') is inside 'assets[{$i}].dir' ('{$a->dir}')");
                }
                if (str_starts_with($a->dir, $b->dir . '/')) {
                    throw new SpecException("{$where}: 'assets[{$i}].dir' ('{$a->dir}') is inside 'assets[{$j}].dir' ('{$b->dir}')");
                }
            }
        }

        return new self($dirs);
    }

    public function isEmpty(): bool
    {
        return $this->dirs === [];
    }

    /** @return list<array{dir: string, storage: string}> */
    public function toArray(): array
    {
        return array_map(static fn (AssetDir $d) => $d->toArray(), $this->dirs);
    }
}
