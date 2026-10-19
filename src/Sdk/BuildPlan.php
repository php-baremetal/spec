<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Sdk;

use PhpBaremetal\Spec\Manifest\Manifest;
use PhpBaremetal\Spec\Sdk\Descriptor\Board;
use PhpBaremetal\Spec\Sdk\Descriptor\Chip;
use PhpBaremetal\Spec\Sdk\Descriptor\Module;
use PhpBaremetal\Spec\Sdk\Descriptor\PhpExtension;
use PhpBaremetal\Spec\Sdk\Descriptor\PhpRelease;

final readonly class BuildPlan
{
    /**
     * @param array<string,string> $sourceDirs extracted third-party sources, name => directory
     * @param array<string,string> $env        values baked into the firmware
     */
    public function __construct(
        public string $projectDir,
        public Manifest $manifest,
        public Sdk $sdk,
        public Board $board,
        public Chip $chip,
        public Module $runtime,
        public Module $storage,
        public PhpRelease $php,
        public string $phpSourceDir,
        public array $sourceDirs,
        public array $env,
        public string $buildDir,
        public bool $clean = false,
        /** @var array<string,string|int|bool> resolved values, defaults included */
        public array $options = [],
        /** @var list<PhpExtension> optional PHP extensions to compile, required ones first */
        public array $phpExtensions = [],
    ) {
    }

    public function srcDir(): string
    {
        return $this->projectDir . '/' . $this->manifest->project->src;
    }

    public function entry(): string
    {
        return $this->manifest->project->entry;
    }
}
