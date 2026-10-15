<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Sdk\Descriptor;

use PhpBaremetal\Spec\Support\Read;

final readonly class SourceArchive
{
    public function __construct(public string $name, public string $url, public string $sha256)
    {
    }

    public static function fromArray(string $name, array $data, string $where): self
    {
        return new self($name, Read::string($data, 'url', $where), Read::sha256($data, 'sha256', $where));
    }
}
