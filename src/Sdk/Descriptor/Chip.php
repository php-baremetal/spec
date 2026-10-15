<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Sdk\Descriptor;

use PhpBaremetal\Spec\Support\Read;

final readonly class Chip
{
    /**
     * @param array<string,list<string>> $capabilities
     * @param list<string>               $psram
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $target,
        public array $capabilities,
        public array $psram,
    ) {
    }

    public static function fromArray(string $id, array $data, string $where): self
    {
        $capabilities = [];
        $raw = Read::map($data, 'capabilities', $where);
        foreach (array_keys($raw) as $capability) {
            $capabilities[(string) $capability] = Read::stringList($raw, (string) $capability, "{$where} capabilities");
        }

        return new self(
            $id,
            Read::string($data, 'name', $where),
            Read::string($data, 'target', $where),
            $capabilities,
            Read::stringList($data, 'psram', $where),
        );
    }

    public function supports(string $capability, string $type): bool
    {
        return in_array($type, $this->capabilities[$capability] ?? [], true);
    }
}
