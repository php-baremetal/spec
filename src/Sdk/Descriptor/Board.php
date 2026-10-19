<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Sdk\Descriptor;

use PhpBaremetal\Spec\SpecException;
use PhpBaremetal\Spec\Support\Read;

final readonly class Board
{
    private const FIELDS = ['name', 'chip', 'psram', 'flash', 'flash-mode', 'revision', 'console'];

    /** @param array<string,array<string,mixed>> $peripherals every block carries a 'type' */
    public function __construct(
        public string $id,
        public string $name,
        public string $chip,
        public ?string $psram,
        public string $flash,
        public ?string $console,
        public array $peripherals,
        /** Flash bus width (e.g. quad, octal); null is the SDK's default. */
        public ?string $flashMode = null,
        /** Chip silicon revision family the firmware targets (e.g. v1, v3); null is the SDK's default. */
        public ?string $revision = null,
    ) {
    }

    public static function fromArray(string $id, array $data, string $where): self
    {
        $peripherals = array_diff_key($data, array_flip(self::FIELDS));
        foreach ($peripherals as $key => $block) {
            if (!is_array($block) || array_is_list($block)) {
                throw new SpecException("{$where}: '{$key}' must be an object");
            }
            if (!is_string($block['type'] ?? null) || $block['type'] === '') {
                throw new SpecException("{$where}: '{$key}.type' must be a non-empty string");
            }
        }

        return new self(
            $id,
            Read::string($data, 'name', $where),
            Read::string($data, 'chip', $where),
            Read::optionalString($data, 'psram', $where),
            Read::string($data, 'flash', $where),
            Read::optionalString($data, 'console', $where),
            $peripherals,
            Read::optionalString($data, 'flash-mode', $where),
            Read::optionalString($data, 'revision', $where),
        );
    }

    public function withOverrides(array $overrides): self
    {
        $peripherals = $this->peripherals;
        foreach ($overrides as $key => $block) {
            if (!isset($peripherals[$key])) {
                throw new SpecException("board-overrides: board '{$this->id}' has no '{$key}' to override");
            }
            if (!is_array($block) || ($block !== [] && array_is_list($block))) {
                throw new SpecException("board-overrides: '{$key}' must be an object");
            }
            if (array_key_exists('type', $block)) {
                throw new SpecException("board-overrides: '{$key}.type' cannot be overridden");
            }
            $peripherals[$key] = array_replace($peripherals[$key], $block);
        }

        return new self($this->id, $this->name, $this->chip, $this->psram, $this->flash, $this->console, $peripherals, $this->flashMode, $this->revision);
    }

    public function has(string $capability): bool
    {
        return isset($this->peripherals[$capability]);
    }

    /** @return array<string,string> capability => type */
    public function capabilities(): array
    {
        return array_map(static fn (array $block) => (string) $block['type'], $this->peripherals);
    }
}
