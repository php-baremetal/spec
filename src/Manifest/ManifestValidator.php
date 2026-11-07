<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Manifest;

use PhpBaremetal\Spec\Sdk\Descriptor\PhpExtension;
use PhpBaremetal\Spec\SpecException;

final class ManifestValidator
{
    /** @return list<string> */
    public function validate(Manifest $manifest): array
    {
        $problems = [];

        $extensions = [];
        foreach ($manifest->ofKind('ext') as $package) {
            $name = $package->meta['module'] ?? null;
            if (!is_string($name) || $name === '') {
                $problems[] = "{$package->name}: extension declares no 'module'";
                continue;
            }
            if (isset($extensions[$name])) {
                $problems[] = "extension '{$name}' provided by both {$extensions[$name]} and {$package->name}";
                continue;
            }
            $extensions[$name] = $package->name;
        }

        $sdk = $manifest->project->sdk->name;
        foreach ($manifest->ofKind('hal') as $package) {
            $platform = $package->meta['platform'] ?? null;
            if ($platform === null) {
                $problems[] = "{$package->name}: type bm-hal needs 'platform' in extra.baremetal";
            } elseif ($platform !== $sdk) {
                $shown = is_string($platform) ? $platform : get_debug_type($platform);
                $problems[] = "{$package->name}: 'platform' is '{$shown}' but the project's sdk is '{$sdk}'";
            }
        }

        foreach ($manifest->packages as $package) {
            if (!array_key_exists('board-blocks', $package->meta)) {
                continue;
            }
            $blocks = $package->meta['board-blocks'];
            if (!is_array($blocks) || !array_is_list($blocks)) {
                $problems[] = "{$package->name}: extra.baremetal.board-blocks must be a list of '<kind>:<type>' strings";
                continue;
            }
            foreach ($blocks as $block) {
                if (!is_string($block) || !preg_match('/^[a-z0-9]+:[a-z0-9][a-z0-9-]*$/D', $block)) {
                    $problems[] = "{$package->name}: extra.baremetal.board-blocks must be a list of '<kind>:<type>' strings";
                    break;
                }
            }
        }

        foreach ($manifest->packages as $package) {
            if (!array_key_exists('ide-stubs', $package->meta)) {
                continue;
            }
            $stubs = $package->meta['ide-stubs'];
            $valid = is_array($stubs) && array_is_list($stubs);
            if ($valid) {
                foreach ($stubs as $stub) {
                    if (!is_string($stub) || !self::isRelativeStubPath($stub)) {
                        $valid = false;
                        break;
                    }
                }
            }
            if (!$valid) {
                $problems[] = "{$package->name}: extra.baremetal.ide-stubs must be a list of relative paths ending in .stub";
            }
        }

        foreach ($manifest->packages as $package) {
            if (!array_key_exists('options', $package->meta)) {
                continue;
            }
            try {
                $options = $package->options();
            } catch (SpecException $e) {
                $problems[] = $e->getMessage();
                continue;
            }
            $prefix = $package->optionPrefix();
            foreach (array_keys($options) as $name) {
                if ($prefix !== null && !str_starts_with($name, "{$prefix}-")) {
                    $problems[] = "{$package->name}: option '{$name}' must start with '{$prefix}-'";
                }
            }
        }

        foreach ($manifest->ofKind('php-ext') as $package) {
            $keys = array_keys($package->meta);
            if ($keys !== ['php-ext']) {
                $extra = array_values(array_diff($keys, ['php-ext']));
                $problems[] = $extra === []
                    ? "{$package->name}: type bm-php-ext needs 'php-ext' in extra.baremetal"
                    : "{$package->name}: type bm-php-ext allows only 'php-ext' in extra.baremetal, got '{$extra[0]}'";
                continue;
            }
            $name = $package->meta['php-ext'];
            if (!is_string($name) || !preg_match(PhpExtension::NAME, $name)) {
                $problems[] = "{$package->name}: 'php-ext' must name a PHP extension ([a-z][a-z0-9_]*)";
            }
        }

        return $problems;
    }

    /** True for a relative path (no leading slash, drive letter or ".." segment) ending in .stub. */
    public static function isRelativeStubPath(string $path): bool
    {
        if ($path === '' || $path[0] === '/' || $path[0] === '\\' || preg_match('/^[A-Za-z]:/', $path)) {
            return false;
        }
        if (!str_ends_with($path, '.stub')) {
            return false;
        }
        foreach (preg_split('#[/\\\\]#', $path) as $segment) {
            if ($segment === '..') {
                return false;
            }
        }
        return true;
    }
}
