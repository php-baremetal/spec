<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Manifest;

use PhpBaremetal\Spec\SpecException;
use PhpBaremetal\Spec\Support\Read;

final readonly class Fonts
{
    private const KEYS = ['default', 'dir', 'bake'];

    public function __construct(
        public bool $default = true,
        public string $dir = 'project-fonts',
        /** @var list<FontBake> */
        public array $bake = [],
    ) {
    }

    public static function fromArray(array $data, string $where): self
    {
        $fonts = Read::map($data, 'fonts', $where);
        foreach (array_keys($fonts) as $key) {
            if (!in_array($key, self::KEYS, true)) {
                throw new SpecException("{$where}: unknown key '{$key}' in fonts (allowed: " . implode(', ', self::KEYS) . ')');
            }
        }

        $default = $fonts['default'] ?? true;
        if (!is_bool($default)) {
            throw new SpecException("{$where}: 'fonts.default' must be true or false");
        }

        $dir = $fonts['dir'] ?? 'project-fonts';
        if (
            !is_string($dir) || $dir === '' || preg_match('/^([\/\\\\]|[A-Za-z]:)/', $dir)
            || in_array('..', preg_split('/[\/\\\\]/', $dir), true)
        ) {
            throw new SpecException("{$where}: 'fonts.dir' must be a relative path inside the project");
        }

        $list = $fonts['bake'] ?? [];
        if (!is_array($list) || !array_is_list($list)) {
            throw new SpecException("{$where}: 'fonts.bake' must be a list");
        }
        $bake = [];
        foreach ($list as $i => $entry) {
            if (!is_array($entry) || ($entry !== [] && array_is_list($entry))) {
                throw new SpecException("{$where}: 'fonts.bake[{$i}]' must be an object");
            }
            $bake[] = FontBake::fromArray($entry, "{$where} fonts.bake[{$i}]");
        }

        return new self($default, $dir, $bake);
    }

    public function isDefault(): bool
    {
        return $this->default === true && $this->dir === 'project-fonts' && $this->bake === [];
    }

    public function toArray(): array
    {
        return [
            'default' => $this->default,
            'dir' => $this->dir,
            'bake' => array_map(static fn (FontBake $b) => $b->toArray(), $this->bake),
        ];
    }
}
