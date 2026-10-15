<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Sdk\Descriptor;

use PhpBaremetal\Spec\Support\Read;

final readonly class Module
{
    /** @param 'runtime'|'storage' $kind */
    public function __construct(
        public string $kind,
        public string $id,
        public string $description,
        public Requirements $requires,
    ) {
    }

    public static function fromArray(string $kind, string $id, array $data, string $where): self
    {
        return new self(
            $kind,
            $id,
            Read::string($data, 'description', $where),
            Requirements::fromArray(Read::map($data, 'requires', $where), "{$where} requires"),
        );
    }
}
