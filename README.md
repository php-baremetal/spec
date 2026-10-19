# spec

`php-baremetal/spec` is the contract between the tools and the SDKs of php-baremetal: a PHP library, published on
[Packagist](https://packagist.org/packages/php-baremetal/spec), that defines the build manifest of a project and the
interface an SDK driver implements. [`conductor`](https://packagist.org/packages/php-baremetal/conductor) writes the
manifest with it, `phpbm` reads it and loads SDKs with it, and an SDK such as `esp32-sdk` implements its driver
against it. It has no runtime dependency beyond PHP 8.4.

You need it directly only when you write a tool that reads a manifest, or an SDK driver for another platform.

```bash
composer require php-baremetal/spec
```

## Manifest

`PhpBaremetal\Spec\Manifest\Manifest` is `.phpbm/manifest.json` (`Manifest::PATH`), schema `Manifest::SCHEMA` (1):

- `Project`: `name`, `sdk` (a `SdkRequirement`, `<name>@<constraint>`), `board`, `runtime`, `storage`, `php`
  (a version such as `8.5.11`, `phpMinor()` gives `8.5`), `src`, `entry`, `env`, `boardOverrides` and `options`.
  `Project::fromComposer()` builds it from `extra.baremetal` and `config.platform.php`.
- `Package`: `name`, `type` (always `bm-*`), `version`, `path` and `meta` (the package's `extra.baremetal`).
  `kind()` returns the type without `bm-`.
- `Manifest::fromFile()`, `fromArray()`, `toJson()`, `ofKind('ext')`, `has($name)`.
- `ManifestValidator::validate()` returns a list of problems: extensions without a `module` or sharing one, a
  `bm-hal` for another platform than the SDK's, a `bm-php-ext` that carries more than `php-ext`.

## SDK

An SDK is a directory (`sdk.json`, `chips/`, `boards/`, `runtimes/`, `storages/`, `php/<minor>/extensions/*.json`,
`sources/`) plus a driver class.

- `Sdk\SdkLoader::load($dir)` reads the directory into an `Sdk` and checks it: boards against their chip, extension
  modules and entry symbols for clashes, extension requirements for cycles. The descriptor classes are in
  `Sdk\Descriptor` (`SdkDescriptor`, `Chip`, `Board`, `Module`, `Option`, `PhpExtension`, `PhpRelease`,
  `Requirements`, `SourceArchive`).
- `Sdk\Driver` is the interface an SDK driver implements, `Driver::CONTRACT` being the contract version (1) that
  `sdk.json` must declare:

  | Method | Meaning |
  | --- | --- |
  | `toolchain()` | A `Toolchain`: `status()`, `install()`, `env()`. |
  | `build($plan, $out)` | Build the firmware from a `BuildPlan`; returns `Artifacts`. |
  | `flash($artifacts, $plan, $port, $out)` | Write the images to the board on a `Port`. |
  | `sync($plan, $port, $out)` | Copy the project sources to a storage the firmware does not carry; `false` for the others. |
  | `monitor($port)` | Open the serial console; returns the exit code. |
  | `identify($port)` | A `ChipInfo`, or `null` when the chip does not belong to this platform. |
  | `ports()` | The serial ports found. |

  The driver is constructed with a `Context` (SDK directory, toolchain directory, a `Process` to run commands), and
  reports progress through an `Output`.
- `Sdk::driver($context)` loads the driver class named in `sdk.json` (`driver.class`, autoloaded from
  `driver.autoload`), refusing a contract version it does not support.

## Testing an SDK

`Testing\DriverConformanceTestCase` (needs PHPUnit) is a base class: extend it, return the SDK directory from
`sdkDir()`, and it checks the contract version, that the driver implements `Driver`, and that `toolchain()`,
`ports()` and `identify()` behave. `Testing\FakeProcess` and `Testing\BufferedOutput` stand in for the process runner
and the output.

## License

MIT.
