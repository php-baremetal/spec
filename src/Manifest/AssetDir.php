<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Manifest;

use PhpBaremetal\Spec\SpecException;

final readonly class AssetDir
{
    private const KEYS = ['dir', 'storage'];

    public function __construct(
        public string $dir,
        public string $storage,
    ) {
    }

    /** @param string $label the entry as named in messages, e.g. "assets[0]" */
    public static function fromArray(array $data, string $where, string $label): self
    {
        foreach (array_keys($data) as $key) {
            if (!in_array($key, self::KEYS, true)) {
                throw new SpecException("{$where}: unknown key '{$key}' in {$label} (allowed: " . implode(', ', self::KEYS) . ')');
            }
        }

        $dir = $data['dir'] ?? null;
        $message = "{$where}: '{$label}.dir' must be a non-empty relative path inside the project";
        if (!is_string($dir) || $dir === '' || preg_match('/^([\/\\\\]|[A-Za-z]:)/', $dir)) {
            throw new SpecException($message);
        }
        $parts = [];
        foreach (explode('/', str_replace('\\', '/', $dir)) as $part) {
            if ($part === '..') {
                throw new SpecException($message);
            }
            if ($part !== '' && $part !== '.') {
                $parts[] = $part;
            }
        }
        if ($parts === []) {
            throw new SpecException($message);
        }

        $storage = $data['storage'] ?? null;
        if (!is_string($storage) || $storage === '') {
            throw new SpecException("{$where}: '{$label}.storage' must be a non-empty string");
        }

        return new self(implode('/', $parts), $storage);
    }

    public function toArray(): array
    {
        return ['dir' => $this->dir, 'storage' => $this->storage];
    }
}
