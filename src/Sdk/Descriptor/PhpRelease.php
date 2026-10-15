<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Sdk\Descriptor;

use PhpBaremetal\Spec\SpecException;
use PhpBaremetal\Spec\Support\Read;

final readonly class PhpRelease
{
    public function __construct(
        public string $minor,
        public string $version,
        public string $url,
        public string $sha256,
    ) {
    }

    public static function fromArray(string $minor, array $data, string $where): self
    {
        $version = Read::string($data, 'version', $where);
        if (!str_starts_with($version, $minor . '.')) {
            throw new SpecException("{$where}: 'version' {$version} is not a {$minor} release");
        }
        return new self($minor, $version, Read::string($data, 'url', $where), Read::sha256($data, 'sha256', $where));
    }
}
