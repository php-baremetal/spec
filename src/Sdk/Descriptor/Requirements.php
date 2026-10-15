<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Sdk\Descriptor;

use PhpBaremetal\Spec\Support\Read;

final readonly class Requirements
{
    /**
     * @param list<string> $capabilities
     * @param list<string> $packages
     */
    public function __construct(public array $capabilities = [], public array $packages = [])
    {
    }

    public static function fromArray(array $data, string $where): self
    {
        return new self(
            Read::stringList($data, 'capabilities', $where),
            Read::stringList($data, 'packages', $where),
        );
    }
}
