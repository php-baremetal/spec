<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Support;

use PhpBaremetal\Spec\SpecException;

final class Read
{
    public static function string(array $data, string $key, string $where): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new SpecException("{$where}: '{$key}' must be a non-empty string");
        }
        return $value;
    }

    public static function optionalString(array $data, string $key, string $where): ?string
    {
        return ($data[$key] ?? null) === null ? null : self::string($data, $key, $where);
    }

    public static function int(array $data, string $key, string $where): int
    {
        $value = $data[$key] ?? null;
        if (!is_int($value)) {
            throw new SpecException("{$where}: '{$key}' must be an integer");
        }
        return $value;
    }

    /** @return list<string> */
    public static function stringList(array $data, string $key, string $where): array
    {
        $value = $data[$key] ?? [];
        if (!is_array($value) || !array_is_list($value)) {
            throw new SpecException("{$where}: '{$key}' must be a list of strings");
        }
        foreach ($value as $item) {
            if (!is_string($item) || $item === '') {
                throw new SpecException("{$where}: '{$key}' must be a list of strings");
            }
        }
        return $value;
    }

    public static function map(array $data, string $key, string $where): array
    {
        $value = $data[$key] ?? [];
        if (!is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new SpecException("{$where}: '{$key}' must be an object");
        }
        return $value;
    }

    public static function sha256(array $data, string $key, string $where): string
    {
        $value = self::string($data, $key, $where);
        if (!preg_match('/^[a-f0-9]{64}$/', $value)) {
            throw new SpecException("{$where}: '{$key}' must be a lowercase hex sha256");
        }
        return $value;
    }
}
