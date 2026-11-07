<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Manifest;

use PhpBaremetal\Spec\SpecException;

final readonly class FontBake
{
    private const KEYS = ['name', 'source', 'size', 'bpp', 'chars', 'range'];

    public function __construct(
        public string $name,
        public string $source,
        public ?int $size = null,
        public int $bpp = 1,
        public ?string $chars = null,
        public ?string $range = null,
    ) {
    }

    public static function fromArray(array $data, string $where): self
    {
        $root = explode(' ', $where)[0];
        $path = substr($where, strlen($root) + 1);
        foreach (array_keys($data) as $key) {
            if (!in_array($key, self::KEYS, true)) {
                throw new SpecException("{$root}: unknown key '{$key}' in {$path} (allowed: " . implode(', ', self::KEYS) . ')');
            }
        }

        $name = $data['name'] ?? null;
        if (!is_string($name) || !preg_match('/^[a-z0-9][a-z0-9-]*$/D', $name)) {
            throw new SpecException("{$root}: '{$path}.name' must match [a-z0-9][a-z0-9-]*");
        }

        $source = $data['source'] ?? null;
        if (!is_string($source) || !preg_match('/\.(ttf|bdf)$/Di', $source)) {
            throw new SpecException("{$root}: '{$path}.source' must be a .ttf or .bdf file");
        }

        $size = $data['size'] ?? null;
        if ($size !== null && (!is_int($size) || $size < 4 || $size > 256)) {
            throw new SpecException("{$root}: '{$path}.size' must be an integer between 4 and 256");
        }

        $bpp = $data['bpp'] ?? 1;
        if (!is_int($bpp) || !in_array($bpp, [1, 2, 4], true)) {
            throw new SpecException("{$root}: '{$path}.bpp' must be 1, 2 or 4");
        }

        $chars = $data['chars'] ?? null;
        $range = $data['range'] ?? null;
        if ($chars !== null && $range !== null) {
            throw new SpecException("{$root}: '{$path}' takes chars or range, not both");
        }
        if ($chars !== null && (!is_string($chars) || $chars === '')) {
            throw new SpecException("{$root}: '{$path}.chars' must be a non-empty string");
        }
        if ($range !== null && !self::validRange($range)) {
            throw new SpecException("{$root}: '{$path}.range' must look like 0x20-0x7E");
        }

        return new self($name, $source, $size, $bpp, $chars, $range);
    }

    private static function validRange(mixed $range): bool
    {
        $hex = '0x[0-9a-fA-F]+';
        if (!is_string($range) || !preg_match("/^{$hex}-{$hex}(,{$hex}-{$hex})*$/D", $range)) {
            return false;
        }
        foreach (explode(',', $range) as $interval) {
            [$from, $to] = explode('-', $interval);
            if (hexdec($from) > hexdec($to)) {
                return false;
            }
        }
        return true;
    }

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'source' => $this->source,
            'size' => $this->size,
            'bpp' => $this->bpp,
            'chars' => $this->chars,
            'range' => $this->range,
        ], static fn ($value) => $value !== null);
    }
}
