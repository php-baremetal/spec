<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Sdk\Descriptor;

use PhpBaremetal\Spec\SpecException;
use PhpBaremetal\Spec\Support\Read;

/**
 * An optional PHP extension the sdk can compile for one PHP minor (php/<minor>/extensions/<name>.json).
 * Paths are relative to the PHP source tree, or to an external source when written "<source>:<path>".
 */
final readonly class PhpExtension
{
    public const NAME = '/^[a-z][a-z0-9_]*$/D';
    /** A PHP module name (what extension_loaded() reports), the same rule as a bm-ext package's 'module'. */
    public const MODULE = '/^[a-z_][a-z0-9_]*$/D';
    /** The C symbol of a zend_module_entry. */
    public const ENTRY = '/^[A-Za-z_][A-Za-z0-9_]*$/D';
    /** Modules every firmware has, in every minor (date is an optional extension, not built in). */
    public const BUILTIN = ['core', 'hash', 'json', 'pcre', 'random', 'reflection', 'spl', 'standard'];
    /** Modules built in from a minor on: uri is always compiled in from PHP 8.5. */
    public const BUILTIN_SINCE = ['uri' => '8.5'];
    /** The real zend_module_entry symbol of each built-in module (not always <module>_module_entry). */
    public const BUILTIN_ENTRY = [
        'core' => 'zend_builtin_module',
        'hash' => 'hash_module_entry',
        'json' => 'json_module_entry',
        'pcre' => 'pcre_module_entry',
        'random' => 'random_module_entry',
        'reflection' => 'reflection_module_entry',
        'spl' => 'spl_module_entry',
        'standard' => 'basic_functions_module',
        'uri' => 'uri_module_entry',
    ];
    private const KEYS = ['cost', 'defines', 'entry', 'exclude', 'idf-dependencies', 'idf-requires', 'include-dirs', 'module', 'options', 'requires', 'sources', 'startup-kb'];
    private const IDF_COMPONENT = '/^[A-Za-z0-9_][A-Za-z0-9_.-]*$/D';
    private const IDF_REGISTRY_COMPONENT = '#^[A-Za-z0-9_][A-Za-z0-9_.-]*/[A-Za-z0-9_][A-Za-z0-9_.-]*$#D';
    private const IDF_EXACT_VERSION = '/^==\d+\.\d+\.\d+(~\d+)?$/D';
    private const WHEN_KEYS = ['defines', 'exclude', 'include-dirs', 'sources', 'startup-kb'];

    /**
     * @param list<string>                $sources
     * @param list<string>                $exclude
     * @param list<string>                $includeDirs
     * @param array<string,string|int>    $defines
     * @param list<string>                $requiresExtensions
     * @param list<string>                $requiresSources
     * @param array<string,Option>        $options
     * @param array<string,array<string,array{defines:array<string,string|int>,sources:list<string>,exclude:list<string>,includeDirs:list<string>,startupKb:int}>> $when option => value => effect
     * @param list<string>                $idfRequires     ESP-IDF components the php component requires when the extension is active
     * @param array<string,string>        $idfDependencies registry components (namespace/name => "==<version>"), exact pins
     * @param int                         $startupKb       KB of persistent memory the extension allocates at engine startup ('when' values add to it)
     * @param string                      $module          the PHP module name
     * @param ?string                     $entry           the zend_module_entry symbol, null for an extension that registers itself
     */
    public function __construct(
        public string $name,
        public string $minor,
        public string $module,
        public ?string $entry,
        public array $sources,
        public array $exclude = [],
        public array $includeDirs = [],
        public array $defines = [],
        public array $requiresExtensions = [],
        public array $requiresSources = [],
        public array $options = [],
        public array $when = [],
        public ?string $cost = null,
        public array $idfRequires = [],
        public array $idfDependencies = [],
        public int $startupKb = 0,
    ) {
    }

    /**
     * The built-in modules of a PHP minor; without a minor, the union of every minor (for callers that
     * check a name before the minor is known).
     *
     * @return list<string>
     */
    public static function builtin(?string $minor = null): array
    {
        $names = self::BUILTIN;
        foreach (self::BUILTIN_SINCE as $name => $since) {
            if ($minor === null || version_compare($minor, $since, '>=')) {
                $names[] = $name;
            }
        }
        return $names;
    }

    /** @return array<string,string> built-in module => its zend_module_entry symbol */
    public static function builtinEntries(?string $minor = null): array
    {
        $entries = [];
        foreach (self::builtin($minor) as $module) {
            $entries[$module] = self::BUILTIN_ENTRY[$module];
        }
        return $entries;
    }

    public static function fromArray(string $minor, string $name, array $data, string $where): self
    {
        if (!preg_match(self::NAME, $name)) {
            throw new SpecException("{$where}: extension name '{$name}' must match [a-z][a-z0-9_]*");
        }
        if (in_array($name, self::builtin(), true)) {
            throw new SpecException("{$where}: '{$name}' is a built-in PHP extension");
        }
        foreach (array_keys($data) as $key) {
            if (!in_array($key, self::KEYS, true)) {
                throw new SpecException("{$where}: unknown key '{$key}' (allowed: " . implode(', ', self::KEYS) . ')');
            }
        }
        if (!array_key_exists('module', $data)) {
            throw new SpecException("{$where}: 'module' is required (the PHP module name)");
        }
        $module = Read::string($data, 'module', $where);
        if (!preg_match(self::MODULE, $module)) {
            throw new SpecException("{$where}: 'module' must be the PHP module name ([a-z_][a-z0-9_]*), got '{$module}'");
        }
        // 'entry': the zend_module_entry symbol when it is not "<module>_module_entry"; null for a Zend
        // extension that an sdk patch starts and that registers its module itself (opcache 8.3/8.4).
        $entry = "{$module}_module_entry";
        if (array_key_exists('entry', $data)) {
            $entry = Read::optionalString($data, 'entry', $where);
            if ($entry !== null && !preg_match(self::ENTRY, $entry)) {
                throw new SpecException("{$where}: 'entry' must be the C symbol of the zend_module_entry, got '{$entry}'");
            }
        }

        $requires = Read::map($data, 'requires', $where);
        foreach (array_keys($requires) as $key) {
            if ($key !== 'extensions' && $key !== 'sources') {
                throw new SpecException("{$where}: unknown key '{$key}' in 'requires' (allowed: extensions, sources)");
            }
        }
        $requiresExtensions = Read::stringList($requires, 'extensions', "{$where} requires");
        foreach ($requiresExtensions as $extension) {
            if (!preg_match(self::NAME, $extension) || $extension === $name) {
                throw new SpecException("{$where}: invalid required extension '{$extension}'");
            }
        }
        $requiresSources = Read::stringList($requires, 'sources', "{$where} requires");

        $paths = static fn (array $from, string $key, string $at): array => array_map(
            static fn (string $path): string => self::path($path, $key, $requiresSources, $at),
            Read::stringList($from, $key, $at),
        );
        // Without 'sources' the extension's code is all in php/<minor>/compat/extensions/<name> (the driver checks it compiles something).
        $sources = $paths($data, 'sources', $where);
        if ($sources === [] && array_key_exists('sources', $data)) {
            throw new SpecException("{$where}: 'sources' must list at least one file pattern (omit it when all the code is in compat/extensions/{$name})");
        }

        $idfRequires = Read::stringList($data, 'idf-requires', $where);
        foreach ($idfRequires as $component) {
            if (!preg_match(self::IDF_COMPONENT, $component)) {
                throw new SpecException("{$where}: invalid ESP-IDF component name '{$component}' in 'idf-requires'");
            }
        }
        $idfDependencies = Read::map($data, 'idf-dependencies', $where);
        foreach ($idfDependencies as $component => $version) {
            if (!preg_match(self::IDF_REGISTRY_COMPONENT, (string) $component)) {
                throw new SpecException("{$where}: 'idf-dependencies' key '{$component}' must be a registry component <namespace>/<name>");
            }
            if (!is_string($version) || !preg_match(self::IDF_EXACT_VERSION, $version)) {
                throw new SpecException("{$where}: 'idf-dependencies' must pin '{$component}' to an exact version (==x.y.z or ==x.y.z~n)");
            }
        }
        ksort($idfDependencies, SORT_STRING);

        $options = [];
        $when = [];
        foreach (Read::map($data, 'options', $where) as $option => $spec) {
            $option = (string) $option;
            $at = "{$where} option {$option}";
            if (!is_array($spec)) {
                throw new SpecException("{$at}: must be an object");
            }
            $effects = Read::map($spec, 'when', $at);
            unset($spec['when']);
            $options[$option] = $parsed = Option::fromArray($option, $spec, $at);
            if ($effects === []) {
                continue;
            }
            $values = match ($parsed->type) {
                'enum' => $parsed->values,
                'bool' => ['true', 'false'],
                default => throw new SpecException("{$at}: 'when' is only allowed on enum and bool options"),
            };
            foreach ($effects as $value => $effect) {
                $value = (string) $value;
                if (!in_array($value, $values, true)) {
                    throw new SpecException("{$at}: 'when' value '{$value}' must be one of " . implode(', ', $values));
                }
                if (!is_array($effect) || ($effect !== [] && array_is_list($effect))) {
                    throw new SpecException("{$at}: 'when.{$value}' must be an object");
                }
                foreach (array_keys($effect) as $key) {
                    if (!in_array($key, self::WHEN_KEYS, true)) {
                        throw new SpecException("{$at}: unknown key '{$key}' in 'when.{$value}' (allowed: " . implode(', ', self::WHEN_KEYS) . ')');
                    }
                }
                $when[$option][$value] = [
                    'defines' => self::defines($effect, "{$at} when.{$value}"),
                    'sources' => $paths($effect, 'sources', "{$at} when.{$value}"),
                    'exclude' => $paths($effect, 'exclude', "{$at} when.{$value}"),
                    'includeDirs' => $paths($effect, 'include-dirs', "{$at} when.{$value}"),
                    'startupKb' => self::startupKb($effect, "{$at} when.{$value}"),
                ];
            }
        }

        $cost = null;
        if (array_key_exists('cost', $data)) {
            $cost = Read::string($data, 'cost', $where);
        }

        return new self(
            $name,
            $minor,
            $module,
            $entry,
            $sources,
            $paths($data, 'exclude', $where),
            $paths($data, 'include-dirs', $where),
            self::defines($data, $where),
            $requiresExtensions,
            $requiresSources,
            $options,
            $when,
            $cost,
            array_values(array_unique($idfRequires)),
            $idfDependencies,
            self::startupKb($data, $where),
        );
    }

    /**
     * The build of this extension with the given option values (missing ones take their default).
     * Files are the sources of each layer minus its excludes, layer after layer: first the
     * descriptor's own, then each matching 'when' in option order, which can add back an excluded file.
     *
     * @param array<string,string|int|bool> $values
     * @return array{layers:list<array{sources:list<string>,exclude:list<string>}>,includeDirs:list<string>,defines:array<string,string|int>,startupKb:int}
     */
    public function resolve(array $values): array
    {
        $layers = [['sources' => $this->sources, 'exclude' => $this->exclude]];
        $includeDirs = $this->includeDirs;
        $defines = $this->defines;
        $startupKb = $this->startupKb;
        foreach ($this->when as $option => $effects) {
            $value = $values[$option] ?? $this->options[$option]->default;
            $effect = $effects[is_bool($value) ? ($value ? 'true' : 'false') : (string) $value] ?? null;
            if ($effect !== null) {
                $layers[] = ['sources' => $effect['sources'], 'exclude' => $effect['exclude']];
                $includeDirs = array_values(array_unique([...$includeDirs, ...$effect['includeDirs']]));
                $defines = [...$defines, ...$effect['defines']];
                $startupKb += $effect['startupKb'];
            }
        }
        return ['layers' => $layers, 'includeDirs' => $includeDirs, 'defines' => $defines, 'startupKb' => $startupKb];
    }

    /**
     * The external sources the build with these option values uses: only these need to be prepared.
     *
     * @param array<string,string|int|bool> $values
     * @return list<string>
     */
    public function usedSources(array $values): array
    {
        $build = $this->resolve($values);
        $paths = $build['includeDirs'];
        foreach ($build['layers'] as $layer) {
            $paths = [...$paths, ...$layer['sources'], ...$layer['exclude']];
        }
        $used = [];
        foreach ($paths as $path) {
            [$source] = self::split($path);
            if ($source !== '') {
                $used[$source] = true;
            }
        }
        return array_values(array_filter($this->requiresSources, static fn (string $s): bool => isset($used[$s])));
    }

    /**
     * Splits a path into [source, path]: source '' is the PHP tree.
     *
     * @return array{string,string}
     */
    public static function split(string $path): array
    {
        $colon = strpos($path, ':');
        return $colon === false ? ['', $path] : [substr($path, 0, $colon), substr($path, $colon + 1)];
    }

    /** @param list<string> $sources */
    private static function path(string $path, string $key, array $sources, string $where): string
    {
        [$source, $relative] = self::split($path);
        if ($source !== '' && !in_array($source, $sources, true)) {
            throw new SpecException("{$where}: '{$key}' uses the source '{$source}', which is not in requires.sources");
        }
        $segments = explode('/', str_replace('\\', '/', $relative));
        if ($relative === '' || str_starts_with($relative, '/') || in_array('..', $segments, true) || in_array('', $segments, true)) {
            throw new SpecException("{$where}: '{$key}' must hold relative paths without '..', got '{$path}'");
        }
        // ';' splits CMake lists, '=' the -ffile-prefix-map pairs, quotes the generated CMake strings.
        if (strpbrk($path, ';="') !== false) {
            throw new SpecException("{$where}: '{$key}' must not contain ';', '=' or '\"': '{$path}'");
        }
        if ($key === 'include-dirs' && strpbrk($relative, '*?') !== false) {
            throw new SpecException("{$where}: 'include-dirs' cannot use wildcards: '{$path}'");
        }
        if (preg_match('/[\[\]{}]/', $relative) || strpbrk(dirname($relative), '*?') !== false) {
            throw new SpecException("{$where}: '{$key}' may use only '*' and '?' wildcards, in the last segment: '{$path}'");
        }
        return $path;
    }

    private static function startupKb(array $data, string $where): int
    {
        if (!array_key_exists('startup-kb', $data)) {
            return 0;
        }
        $kb = Read::int($data, 'startup-kb', $where);
        if ($kb < 0) {
            throw new SpecException("{$where}: 'startup-kb' must be >= 0");
        }
        return $kb;
    }

    /** @return array<string,string|int> */
    private static function defines(array $data, string $where): array
    {
        $defines = Read::map($data, 'defines', $where);
        foreach ($defines as $define => $value) {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', (string) $define)) {
                throw new SpecException("{$where}: invalid define name '{$define}'");
            }
            if (!is_int($value) && !(is_string($value) && !preg_match('/[;\x00-\x1F]/', $value))) {
                throw new SpecException("{$where}: define '{$define}' must be an integer or a string without ';' or control characters");
            }
        }
        return $defines;
    }
}
