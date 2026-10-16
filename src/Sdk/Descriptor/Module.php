<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Sdk\Descriptor;

use PhpBaremetal\Spec\SpecException;
use PhpBaremetal\Spec\Support\Read;

final readonly class Module
{
    /** @param 'runtime'|'storage' $kind */
    public function __construct(
        public string $kind,
        public string $id,
        public string $description,
        public Requirements $requires,
        /** @var array<string,Option> */
        public array $options = [],
    ) {
    }

    public static function fromArray(string $kind, string $id, array $data, string $where): self
    {
        $options = [];
        foreach (Read::map($data, 'options', $where) as $name => $option) {
            $name = (string) $name;
            if (!is_array($option)) {
                throw new SpecException("{$where} option {$name}: must be an object");
            }
            $options[$name] = Option::fromArray($name, $option, "{$where} option {$name}");
        }

        return new self(
            $kind,
            $id,
            Read::string($data, 'description', $where),
            Requirements::fromArray(Read::map($data, 'requires', $where), "{$where} requires"),
            $options,
        );
    }
}
