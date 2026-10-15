<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Support;

use PhpBaremetal\Spec\SpecException;

final class Json
{
    public static function decodeFile(string $path): array
    {
        if (!is_file($path)) {
            throw new SpecException("missing file: {$path}");
        }
        try {
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new SpecException("invalid JSON in {$path}: {$e->getMessage()}", 0, $e);
        }
        if (!is_array($data)) {
            throw new SpecException("{$path} must contain a JSON object");
        }
        return $data;
    }

    public static function encode(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    }

    public static function writeAtomic(string $path, string $contents): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new SpecException("cannot create directory {$dir}");
        }
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($tmp, $contents) === false || !rename($tmp, $path)) {
            @unlink($tmp);
            throw new SpecException("cannot write {$path}");
        }
    }
}
