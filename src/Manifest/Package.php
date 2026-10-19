<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Manifest;

use PhpBaremetal\Spec\SpecException;
use PhpBaremetal\Spec\Support\Read;

final readonly class Package
{
    public function __construct(
        public string $name,
        public string $type,
        public string $version,
        public string $path,
        public array $meta,
    ) {
    }

    public static function fromArray(array $data, string $where): self
    {
        $type = Read::string($data, 'type', $where);
        if (!str_starts_with($type, 'bm-')) {
            throw new SpecException("{$where}: 'type' must start with 'bm-', got '{$type}'");
        }

        return new self(
            Read::string($data, 'name', $where),
            $type,
            Read::string($data, 'version', $where),
            Read::string($data, 'path', $where),
            Read::map($data, 'baremetal', $where),
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'version' => $this->version,
            'path' => $this->path,
            'baremetal' => (object) $this->meta,
        ];
    }

    public function kind(): string
    {
        return substr($this->type, 3);
    }
}
