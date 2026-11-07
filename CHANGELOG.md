# Changelog

## 1.1.0

- Added: `extra.baremetal.fonts` in the project (`Manifest\Fonts`, `Manifest\FontBake`): the default font switch, the project font folder and the fonts to bake.
- Added: `BuildPlan::$fonts`, the resolved fonts (name => path of the GFX header) handed to the SDK driver. Optional: a driver of contract 1 that ignores it keeps working.
- Changed: a board block is checked against the chip's `capabilities` only when the chip lists that block; any other block (such as a display or an LED read by a package's driver) takes any type.
- Added: packages may declare extra.baremetal.board-blocks ('<kind>:<type>', e.g. led:ws2812), the board blocks they drive; the manifest validator checks the format.
- Added: native packages may declare options in extra.baremetal.options (same format as the SDK's), named with their module prefix.
