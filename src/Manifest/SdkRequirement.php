<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Manifest;

use PhpBaremetal\Spec\SpecException;

final readonly class SdkRequirement implements \Stringable
{
    public function __construct(public string $name, public string $constraint)
    {
    }

    public static function parse(string $value): self
    {
        if (!preg_match('/^([a-z0-9][a-z0-9-]*)@(\S+)$/', $value, $m)) {
            throw new SpecException("sdk must look like '<name>@<constraint>', got '{$value}'");
        }
        return new self($m[1], $m[2]);
    }

    public function __toString(): string
    {
        return "{$this->name}@{$this->constraint}";
    }
}
