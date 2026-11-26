# Changelog

## 1.4.1

- Fixed: the 1.4.0 package on Packagist was built from an earlier commit and read `boards/catalog.json` of the SDK as a board (`boards/catalog.json: 'esp32.type' must be a non-empty string`). 1.4.1 has the 1.4.0 changes as listed below, including the skip of `boards/catalog.json`.

## 1.4.0

- Added: board packages (Composer type `bm-boards`). `SdkLoader::withBoardPackages($sdk, $packages, $projectDir)` returns the `Sdk` with the boards of the given packages added, read from `boards/<sdk name>/*.json` and checked against their chip like the SDK's boards; errors name the package and the file. Folders of other SDKs and packages of other types are ignored. The manifest validator asks no `extra.baremetal` keys of a `bm-boards` package.
- Added: `Board::$source` (`Board::SDK` or the package name), `Board::fromSdk()` and `Board::qualifiedId()` (`<source>:<id>`). `Board::withOverrides()` keeps the source.
- Added: `Sdk::board()` accepts the long form `<source>:<id>` (`sdk:<id>`, `<vendor>/<package>:<id>`). A short id defined by more than one source fails, naming the sources and the long forms to use. `Sdk::$boards` keys such boards by their long form, the others by their id as before.
- Added: optional `board-packages` in `sdk.json` (`SdkDescriptor::$boardPackages`), the official board packages as a list of Composer package names; empty when missing.
- Changed: `SdkLoader::load()` skips `boards/catalog.json` (`SdkLoader::CATALOG`), the SDK's board catalog (online docs and photo of each board, same format as a board package's). The name is reserved: an SDK board cannot be called `catalog`.

## 1.3.1

- Changed: `Board::withOverrides()` adds a block the board does not have when the entry gives its `type` (a non-empty string), on any board: `{"microsd": {"type": "spi", "cs": 10, ...}}`. Without a `type` such an entry still fails, now with the hint to give one. On a block the board has, a `type` equal to the board's is accepted (no effect) and a different one still fails, naming the board's type. The name of an added block must be a block name (lower-case letters and digits, parts joined by `-`, e.g. `led-2`). Blocks cannot be removed, and the board's top-level fields are not blocks. The keys of an added block are checked by whoever reads it (the SDK driver for its blocks, a package for its own).

## 1.3.0

- Added: `extra.baremetal.assets` in the project (`Manifest\Assets`, `Manifest\AssetDir`): a list of asset folders, each with its `dir` (relative to the project, normalized) and its `storage`. Checked here: the shape, a relative `dir` without `..`, a non-empty `storage`, and no two entries with the same or nested `dir`. The storage value itself is checked by the build tool against the SDK's storages. `Project::$assets` is empty when the key is missing and `toArray()` leaves it out then.
- Added: `Sdk\PreparedAsset` (storage, board path, prepared local file) and two optional `BuildPlan` values: `$assets`, the project's prepared assets of every storage, and `$imageDir`, the folder that goes into the flash image (sources plus embedded assets), read with `imageDir()` (the sources when null). `srcDir()` is unchanged. A driver of contract 1 that ignores them keeps working.

## 1.2.0

- Added: option type "set": a list of allowed values ("type": "set", "values": [...], a list as default), for packages whose option picks several features. A project sets it with a list of strings in extra.baremetal.options (`Manifest\Project` accepts it).

## 1.1.0

- Added: packages may declare extra.baremetal.ide-stubs (relative paths of .stub files with the package's PHP declarations for IDEs); the manifest validator checks the format.
- Added: `ManifestValidator::isRelativeStubPath()` is public, so conductor applies the same rule when it copies the files.
- Added: `extra.baremetal.fonts` in the project (`Manifest\Fonts`, `Manifest\FontBake`): the default font switch, the project font folder and the fonts to bake.
- Added: `BuildPlan::$fonts`, the resolved fonts (name => path of the GFX header) handed to the SDK driver. Optional: a driver of contract 1 that ignores it keeps working.
- Changed: a board block is checked against the chip's `capabilities` only when the chip lists that block; any other block (such as a display or an LED read by a package's driver) takes any type.
- Added: packages may declare extra.baremetal.board-blocks ('<kind>:<type>', e.g. led:ws2812), the board blocks they drive; the manifest validator checks the format.
- Added: native packages may declare options in extra.baremetal.options (same format as the SDK's), named with their module prefix.
