<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Sdk\Descriptor;

use PhpBaremetal\Spec\SpecException;
use PhpBaremetal\Spec\Support\Read;

final readonly class Board
{
    /** Source of the boards in the SDK's boards/ folder; any other source is a Composer package name. */
    public const SDK = 'sdk';

    private const FIELDS = ['name', 'chip', 'psram', 'flash', 'flash-mode', 'revision', 'console'];

    /** Top-level key of the pin names; not a block. */
    private const PINS = 'pins';

    /**
     * @param array<string,array<string,mixed>> $peripherals every block carries a 'type'
     * @param array<string,int> $pins
     */
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
        /** Where the board comes from: Board::SDK or the name of a bm-boards package. */
        public string $source = self::SDK,
        /** Pin name => GPIO number, in the board file's order; block values naming a pin hold its GPIO. */
        public array $pins = [],
    ) {
    }

    public static function fromArray(string $id, array $data, string $where, string $source = self::SDK): self
    {
        $pins = self::pins($data, $where);
        $peripherals = array_diff_key($data, array_flip([...self::FIELDS, self::PINS]));
        foreach ($peripherals as $key => $block) {
            if (!is_array($block) || array_is_list($block)) {
                throw new SpecException("{$where}: '{$key}' must be an object");
            }
            if (!is_string($block['type'] ?? null) || $block['type'] === '') {
                throw new SpecException("{$where}: '{$key}.type' must be a non-empty string");
            }
            $peripherals[$key] = self::resolvePins($block, $pins);
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
            $source,
            $pins,
        );
    }

    /** @return array<string,int> */
    private static function pins(array $data, string $where): array
    {
        if (!array_key_exists(self::PINS, $data)) {
            return [];
        }
        $pins = $data[self::PINS];
        if (!is_array($pins) || ($pins !== [] && array_is_list($pins))) {
            throw new SpecException("{$where}: 'pins' must be an object (pin name => GPIO number)");
        }
        foreach ($pins as $name => $gpio) {
            $name = (string) $name;
            if (!preg_match('/^[A-Z][A-Z0-9_]*$/D', $name)) {
                throw new SpecException("{$where}: pin name '{$name}' is not valid (upper-case letters, digits and '_', starting with a letter, e.g. LED_BUILTIN)");
            }
            if (!is_int($gpio) || $gpio < 0) {
                throw new SpecException("{$where}: pin '{$name}' must be an integer >= 0 (a GPIO number)");
            }
        }
        return $pins;
    }

    /**
     * A block's string values (not its 'type') equal to a pin name become that pin's GPIO.
     *
     * @param array<string,int> $pins
     */
    private static function resolvePins(array $block, array $pins): array
    {
        foreach ($block as $key => $value) {
            if ($key !== 'type' && is_string($value) && isset($pins[$value])) {
                $block[$key] = $pins[$value];
            }
        }
        return $block;
    }

    /**
     * The board after extra.baremetal.board-overrides. An entry for a block the board has replaces the keys it
     * gives (a 'type' may only repeat the board's); an entry for a block the board lacks adds it whole, with its
     * 'type' and a block name (lower-case letters and digits, parts joined by '-'). The top-level fields are not
     * blocks, nor is 'pins', which overrides cannot change. Values naming one of the board's pins become its GPIO.
     * Which names and types a build accepts is up to the build tool and the SDK.
     */
    public function withOverrides(array $overrides): self
    {
        $peripherals = $this->peripherals;
        foreach ($overrides as $key => $block) {
            $key = (string) $key;
            if ($key === self::PINS) {
                throw new SpecException("board-overrides: 'pins' cannot be overridden: the pin names of board '{$this->id}' come from its board file");
            }
            if (in_array($key, self::FIELDS, true)) {
                throw new SpecException("board-overrides: board '{$this->id}' has no '{$key}' to override");
            }
            if (!is_array($block) || ($block !== [] && array_is_list($block))) {
                throw new SpecException("board-overrides: '{$key}' must be an object");
            }
            if (!isset($peripherals[$key])) {
                if (!preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/D', $key)) {
                    throw new SpecException("board-overrides: '{$key}' is not a block name (lower-case letters and digits, parts joined by '-', e.g. led-2)");
                }
                if (!array_key_exists('type', $block)) {
                    throw new SpecException("board-overrides: board '{$this->id}' has no '{$key}' to override: to add the block, give its 'type'");
                }
                if (!is_string($block['type']) || $block['type'] === '') {
                    throw new SpecException("board-overrides: '{$key}.type' must be a non-empty string");
                }
                $peripherals[$key] = self::resolvePins($block, $this->pins);
                continue;
            }
            if (array_key_exists('type', $block) && $block['type'] !== $peripherals[$key]['type']) {
                throw new SpecException("board-overrides: '{$key}.type' cannot be overridden: board '{$this->id}' has a '{$peripherals[$key]['type']}' {$key}");
            }
            $peripherals[$key] = array_replace($peripherals[$key], self::resolvePins($block, $this->pins));
        }

        return new self($this->id, $this->name, $this->chip, $this->psram, $this->flash, $this->console, $peripherals, $this->flashMode, $this->revision, $this->source, $this->pins);
    }

    public function fromSdk(): bool
    {
        return $this->source === self::SDK;
    }

    /** The long id '<source>:<id>' (e.g. sdk:demo, vendor/boards-x:demo), which picks the board among duplicates. */
    public function qualifiedId(): string
    {
        return "{$this->source}:{$this->id}";
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
