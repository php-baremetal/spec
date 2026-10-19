<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Manifest;

use PhpBaremetal\Spec\Sdk\Descriptor\PhpExtension;

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
}
