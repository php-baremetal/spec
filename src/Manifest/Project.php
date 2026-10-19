<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Manifest;

use PhpBaremetal\Spec\SpecException;
use PhpBaremetal\Spec\Support\Read;

final readonly class Project
{
    public function __construct(
        public string $name,
        public SdkRequirement $sdk,
        public string $board,
        public string $runtime,
        public string $storage,
        public string $php,
        public string $src = 'project-src',
        public string $entry = 'index.php',
        public ?string $env = null,
        public array $boardOverrides = [],
        /** @var array<string,string|int|bool> */
        public array $options = [],
    ) {
    }

    public static function fromArray(array $data, string $where = 'project'): self
    {
        $php = Read::string($data, 'php', $where);
        if (!preg_match('/^\d+\.\d+(\.\d+)?$/', $php)) {
            throw new SpecException("{$where}: 'php' must be a version like 8.4.25, got '{$php}'");
        }

        $sdkValue = Read::string($data, 'sdk', $where);
        try {
            $sdk = SdkRequirement::parse($sdkValue);
        } catch (SpecException $e) {
            throw new SpecException("{$where}: " . $e->getMessage(), 0, $e);
        }

        $options = Read::map($data, 'options', $where);
        foreach ($options as $name => $value) {
            if (is_int($name)) {
                throw new SpecException("{$where}: 'options' keys must be option names, got {$name}");
            }
            if (!is_string($value) && !is_int($value) && !is_bool($value)) {
                throw new SpecException("{$where}: option '{$name}' must be a string, an integer or a boolean");
            }
        }

        return new self(
            Read::string($data, 'name', $where),
            $sdk,
            Read::string($data, 'board', $where),
            Read::string($data, 'runtime', $where),
            Read::string($data, 'storage', $where),
            $php,
            Read::optionalString($data, 'src', $where) ?? 'project-src',
            Read::optionalString($data, 'entry', $where) ?? 'index.php',
            Read::optionalString($data, 'env', $where),
            Read::map($data, 'board-overrides', $where),
            $options,
        );
    }

    public static function fromComposer(string $packageName, array $baremetal, ?string $platformPhp): self
    {
        if ($platformPhp === null) {
            throw new SpecException('composer.json: config.platform.php is required (the PHP version of the firmware)');
        }
        $name = null;
        if ($packageName !== '' && $packageName !== '__root__') {
            $slash = strrpos($packageName, '/');
            $name = $slash === false ? $packageName : substr($packageName, $slash + 1);
        }

        return self::fromArray(['name' => $name, ...$baremetal, 'php' => $platformPhp], 'extra.baremetal');
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'sdk' => (string) $this->sdk,
            'board' => $this->board,
            'runtime' => $this->runtime,
            'storage' => $this->storage,
            'php' => $this->php,
            'src' => $this->src,
            'entry' => $this->entry,
            'env' => $this->env,
            'board-overrides' => (object) $this->boardOverrides,
            'options' => (object) $this->options,
        ];
    }

    public function phpMinor(): string
    {
        return implode('.', array_slice(explode('.', $this->php), 0, 2));
    }
}
