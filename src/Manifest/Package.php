<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Manifest;

use PhpBaremetal\Spec\Sdk\Descriptor\Option;
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

    /**
     * The options declared in extra.baremetal.options, in the SDK's option format.
     *
     * @return array<string, Option>
     * @throws SpecException when an option definition is invalid
     */
    public function options(): array
    {
        $declared = $this->meta['options'] ?? [];
        $where = "{$this->name}: extra.baremetal.options";
        if (!is_array($declared) || ($declared !== [] && array_is_list($declared))) {
            throw new SpecException("{$this->name}: extra.baremetal.options must be an object");
        }
        $options = [];
        foreach ($declared as $name => $definition) {
            $name = (string) $name;
            $at = "{$where}.{$name}";
            if (!is_array($definition)) {
                throw new SpecException("{$at}: must be an object");
            }
            $options[$name] = Option::fromArray($name, $definition, $at);
        }

        return $options;
    }

    /** The prefix every option of this package must start with (without the trailing '-'), or null if none applies. */
    public function optionPrefix(): ?string
    {
        if ($this->kind() === 'ext') {
            $module = $this->meta['module'] ?? null;

            return is_string($module) && $module !== '' ? $module : null;
        }
        if ($this->kind() === 'hal' || $this->kind() === 'lib') {
            $name = substr($this->name, (int) strrpos('/' . $this->name, '/'));
            $name = str_starts_with($name, 'esp32-') ? substr($name, 6) : $name;

            return $name !== '' ? $name : null;
        }

        return null;
    }
}
