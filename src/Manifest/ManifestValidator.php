<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Manifest;

final class ManifestValidator
{
    /** @return list<string> */
    public function validate(Manifest $manifest): array
    {
        $problems = [];

        $buses = [];
        foreach ($manifest->ofKind('transport') as $transport) {
            $buses[(string) ($transport->meta['bus'] ?? '')] = true;
        }
        foreach ($manifest->ofKind('driver') as $driver) {
            $bus = $driver->meta['bus'] ?? null;
            if (!is_string($bus) || $bus === '') {
                $problems[] = "{$driver->name}: driver declares no 'bus'";
                continue;
            }
            if (!isset($buses[$bus])) {
                $problems[] = "{$driver->name}: no transport installed for bus '{$bus}'";
            }
            if (!is_string($driver->meta['driver'] ?? null) || $driver->meta['driver'] === '') {
                $problems[] = "{$driver->name}: driver declares no 'driver' name";
            }
        }

        $extensions = [];
        foreach ([...$manifest->ofKind('ext'), ...$manifest->ofKind('component')] as $package) {
            $name = $package->meta['ext'] ?? $package->meta['name'] ?? null;
            if (!is_string($name) || $name === '') {
                $problems[] = "{$package->name}: extension declares no name";
                continue;
            }
            if (isset($extensions[$name])) {
                $problems[] = "extension '{$name}' provided by both {$extensions[$name]} and {$package->name}";
                continue;
            }
            $extensions[$name] = $package->name;
        }

        return $problems;
    }
}
