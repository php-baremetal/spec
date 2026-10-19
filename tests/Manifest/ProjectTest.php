<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Tests\Manifest;

use PhpBaremetal\Spec\Manifest\Project;
use PhpBaremetal\Spec\Manifest\SdkRequirement;
use PhpBaremetal\Spec\SpecException;
use PHPUnit\Framework\TestCase;

final class ProjectTest extends TestCase
{
    private const BAREMETAL = [
        'sdk' => 'esp32@^1.0',
        'board' => 'waveshare-esp32-s3-eth',
        'runtime' => 'event-driven',
        'storage' => 'embedded',
    ];

    public function test_sdk_requirement_parses_name_and_constraint(): void
    {
        $req = SdkRequirement::parse('esp32@^1.0');
        self::assertSame('esp32', $req->name);
        self::assertSame('^1.0', $req->constraint);
        self::assertSame('esp32@^1.0', (string) $req);
    }

    public function test_sdk_requirement_rejects_missing_constraint(): void
    {
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage("sdk must look like '<name>@<constraint>'");
        SdkRequirement::parse('esp32');
    }

    public function test_from_composer_derives_name_and_php(): void
    {
        $project = Project::fromComposer('acme/blink', self::BAREMETAL, '8.4.25');
        self::assertSame('blink', $project->name);
        self::assertSame('8.4.25', $project->php);
        self::assertSame('8.4', $project->phpMinor());
        self::assertSame('project-src', $project->src);
        self::assertSame('index.php', $project->entry);
        self::assertNull($project->env);
    }

    public function test_from_composer_requires_platform_php(): void
    {
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage('config.platform.php');
        Project::fromComposer('acme/blink', self::BAREMETAL, null);
    }

    public function test_from_composer_requires_a_package_name(): void
    {
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage("'name'");
        Project::fromComposer('__root__', self::BAREMETAL, '8.4.25');
    }

    public function test_missing_board_names_the_key(): void
    {
        $data = self::BAREMETAL;
        unset($data['board']);
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage("extra.baremetal: 'board' must be a non-empty string");
        Project::fromComposer('acme/blink', $data, '8.4.25');
    }

    public function test_invalid_sdk_names_the_location(): void
    {
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage("extra.baremetal: sdk must look like '<name>@<constraint>', got 'esp32'");
        Project::fromComposer('acme/blink', [...self::BAREMETAL, 'sdk' => 'esp32'], '8.4.25');
    }

    public function test_options_default_to_none(): void
    {
        self::assertSame([], Project::fromComposer('acme/blink', self::BAREMETAL, '8.4.25')->options);
    }

    public function test_rejects_an_option_that_is_not_scalar(): void
    {
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage("extra.baremetal: option 'on-error' must be a string, an integer or a boolean");
        Project::fromComposer('acme/blink', [...self::BAREMETAL, 'options' => ['on-error' => ['stop']]], '8.4.25');
    }

    public function test_round_trip(): void
    {
        $project = Project::fromComposer('acme/blink', [
            ...self::BAREMETAL,
            'env' => '.env',
            'board-overrides' => ['microsd' => ['cs' => 5]],
            'options' => ['on-error' => 'continue', 'restart-delay' => 0, 'verbose' => true],
        ], '8.4.25');
        self::assertSame(['on-error' => 'continue', 'restart-delay' => 0, 'verbose' => true], $project->options);
        $again = Project::fromArray(json_decode(json_encode($project->toArray()), true));
        self::assertEquals($project, $again);
    }

    public function test_options_with_an_integer_key_are_rejected(): void
    {
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage("extra.baremetal: 'options' keys must be option names, got 0");
        Project::fromComposer('acme/blink', [...self::BAREMETAL, 'options' => ['0' => 'x', 'on-error' => 'stop']], '8.4.25');
    }
}
