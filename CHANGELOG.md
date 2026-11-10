# Changelog

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
