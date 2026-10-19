<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Sdk\Descriptor;

use PhpBaremetal\Spec\SpecException;
use PhpBaremetal\Spec\Support\Read;

final readonly class Option
{
    private const NAME = '/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/D';

    /** @param list<string> $values */
    public function __construct(
        public string $name,
        public string $description,
        public string $type,
        public string|int|bool $default,
        public array $values = [],
        public ?int $min = null,
        public ?int $max = null,
        public ?string $pattern = null,
    ) {
    }

    public static function fromArray(string $name, array $data, string $where): self
    {
        if (!preg_match(self::NAME, $name)) {
            throw new SpecException("{$where}: option name '{$name}' must be lowercase words joined by '-'");
        }
        $description = Read::string($data, 'description', $where);
        if (array_key_exists('pattern', $data) && (array_key_exists('values', $data) || ($data['type'] ?? null) !== 'string')) {
            throw new SpecException("{$where}: 'pattern' is only allowed on string options");
        }
        if (array_key_exists('values', $data)) {
            $values = Read::stringList($data, 'values', $where);
            foreach ($values as $value) {
                if (!preg_match(self::NAME, $value)) {
                    throw new SpecException("{$where}: value '{$value}' must be lowercase words joined by '-'");
                }
            }
            if ($values === []) {
                throw new SpecException("{$where}: 'values' must not be empty");
            }
            $option = new self($name, $description, 'enum', $values[0], $values);
        } else {
            $type = Read::string($data, 'type', $where);
            $option = match ($type) {
                'int' => new self($name, $description, 'int', 0, [], self::bound($data, 'min', $where), self::bound($data, 'max', $where)),
                'bool' => new self($name, $description, 'bool', false),
                'string' => new self($name, $description, 'string', '', [], null, null, self::pattern($data, $where)),
                default => throw new SpecException("{$where}: 'type' must be int, bool or string, or list the allowed 'values'"),
            };
        }
        if (!array_key_exists('default', $data)) {
            throw new SpecException("{$where}: 'default' is required");
        }

        return new self($name, $description, $option->type, $option->accept($data['default'], "{$where} default"), $option->values, $option->min, $option->max, $option->pattern);
    }

    public function accept(mixed $value, string $where): string|int|bool
    {
        return match ($this->type) {
            'enum' => is_string($value) && in_array($value, $this->values, true)
                ? $value
                : throw new SpecException("{$where}: must be one of " . implode(', ', $this->values) . ', got ' . json_encode($value)),
            'bool' => is_bool($value) ? $value : throw new SpecException("{$where}: must be true or false, got " . json_encode($value)),
            'string' => is_string($value) && ($this->pattern === null || preg_match($this->pattern, $value) === 1)
                ? $value
                : throw new SpecException("{$where}: must be a string" . ($this->pattern !== null ? " that must match {$this->pattern}" : '') . ', got ' . json_encode($value)),
            default => is_int($value) && ($this->min === null || $value >= $this->min) && ($this->max === null || $value <= $this->max)
                ? $value
                : throw new SpecException("{$where}: must be an integer between " . ($this->min ?? 'any') . ' and ' . ($this->max ?? 'any') . ', got ' . json_encode($value)),
        };
    }

    private static function pattern(array $data, string $where): ?string
    {
        if (!array_key_exists('pattern', $data)) {
            return null;
        }
        $pattern = Read::string($data, 'pattern', $where);
        if (@preg_match($pattern, '') === false) {
            throw new SpecException("{$where}: 'pattern' is not a valid regular expression");
        }

        return $pattern;
    }

    private static function bound(array $data, string $key, string $where): ?int
    {
        return array_key_exists($key, $data) ? Read::int($data, $key, $where) : null;
    }
}
