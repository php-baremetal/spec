<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Tests\Sdk;

use PhpBaremetal\Spec\Sdk\Descriptor\Option;
use PhpBaremetal\Spec\SpecException;
use PHPUnit\Framework\TestCase;

final class OptionTest extends TestCase
{
    public function test_enum_defaults_to_a_listed_value(): void
    {
        $option = Option::fromArray('on-error', ['description' => 'd', 'values' => ['stop', 'continue'], 'default' => 'continue'], 'runtime x');
        self::assertSame('enum', $option->type);
        self::assertSame('continue', $option->default);
        self::assertSame(['stop', 'continue'], $option->values);
        self::assertSame('stop', $option->accept('stop', 'w'));
    }

    public function test_enum_default_must_be_listed(): void
    {
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage('must be one of stop, continue');
        Option::fromArray('on-error', ['description' => 'd', 'values' => ['stop', 'continue'], 'default' => 'retry'], 'runtime x');
    }

    public function test_int_with_bounds(): void
    {
        $option = Option::fromArray('restart-delay', ['description' => 'd', 'type' => 'int', 'min' => 0, 'max' => 3600, 'default' => 5], 'runtime x');
        self::assertSame('int', $option->type);
        self::assertSame(5, $option->default);
        self::assertSame(3600, $option->max);
    }

    public function test_int_default_out_of_range(): void
    {
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage('must be an integer between 0 and 3600');
        Option::fromArray('restart-delay', ['description' => 'd', 'type' => 'int', 'min' => 0, 'max' => 3600, 'default' => 4000], 'runtime x');
    }

    public function test_bool(): void
    {
        $option = Option::fromArray('verbose', ['description' => 'd', 'type' => 'bool', 'default' => true], 'runtime x');
        self::assertSame('bool', $option->type);
        self::assertTrue($option->default);
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage('must be true or false');
        $option->accept('yes', 'w');
    }

    public function test_string_with_a_pattern(): void
    {
        $option = Option::fromArray('init', ['description' => 'd', 'type' => 'string', 'pattern' => '#^[a-z]*$#D', 'default' => ''], 'runtime x');
        self::assertSame('string', $option->type);
        self::assertSame('', $option->default);
        self::assertSame('#^[a-z]*$#D', $option->pattern);
        self::assertSame('abc', $option->accept('abc', 'w'));
        self::assertSame('x', Option::fromArray('s', ['description' => 'd', 'type' => 'string', 'default' => 'x'], 'w')->default);
    }

    public function test_string_default_must_match_the_pattern(): void
    {
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage('must match #^[a-z]+$#D');
        Option::fromArray('init', ['description' => 'd', 'type' => 'string', 'pattern' => '#^[a-z]+$#D', 'default' => '1'], 'runtime x');
    }

    public function test_string_pattern_must_be_a_regex(): void
    {
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage("'pattern' is not a valid regular expression");
        Option::fromArray('init', ['description' => 'd', 'type' => 'string', 'pattern' => '(', 'default' => ''], 'runtime x');
    }

    public function test_pattern_only_on_string_options(): void
    {
        foreach ([['type' => 'int', 'default' => 1], ['type' => 'bool', 'default' => true], ['values' => ['a', 'b'], 'default' => 'a']] as $data) {
            try {
                Option::fromArray('x', ['description' => 'd', 'pattern' => '#^a$#D', ...$data], 'runtime x');
                self::fail('expected a SpecException');
            } catch (SpecException $e) {
                self::assertSame("runtime x: 'pattern' is only allowed on string options", $e->getMessage());
            }
        }
    }

    public function test_string_accept_rejects_other_types_and_mismatches(): void
    {
        $option = new Option('s', 'd', 'string', '', [], null, null, '#^[a-z]*$#D');
        foreach ([5, 'ABC'] as $bad) {
            try {
                $option->accept($bad, 'w');
                self::fail('expected a SpecException');
            } catch (SpecException $e) {
                self::assertStringContainsString('must be a string that must match #^[a-z]*$#D', $e->getMessage());
            }
        }
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage('must be a string, got 5');
        (new Option('s', 'd', 'string', ''))->accept(5, 'w');
    }

    public function test_unknown_type(): void
    {
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage("'type' must be int, bool or string");
        Option::fromArray('x', ['description' => 'd', 'type' => 'float', 'default' => 1], 'runtime x');
    }

    public function test_default_is_required(): void
    {
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage("'default' is required");
        Option::fromArray('x', ['description' => 'd', 'type' => 'bool'], 'runtime x');
    }

    public function test_invalid_name(): void
    {
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage("lowercase words joined by '-'");
        Option::fromArray('On_Error', ['description' => 'd', 'type' => 'bool', 'default' => true], 'runtime x');
    }

    public function test_invalid_enum_value_name(): void
    {
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage("value 'Stop' must be lowercase words joined by '-'");
        Option::fromArray('x', ['description' => 'd', 'values' => ['Stop'], 'default' => 'Stop'], 'runtime x');
    }

    public function test_accept_rejects_wrong_types_and_ranges(): void
    {
        $int = new Option('n', 'd', 'int', 0, [], 0, 5);
        self::assertSame(3, $int->accept(3, 'w'));
        foreach (['5', 7, true] as $bad) {
            try {
                $int->accept($bad, 'w');
                self::fail('expected a SpecException');
            } catch (SpecException $e) {
                self::assertStringContainsString('must be an integer between 0 and 5', $e->getMessage());
            }
        }

        $enum = new Option('e', 'd', 'enum', 'a', ['a', 'b']);
        $this->expectException(SpecException::class);
        $this->expectExceptionMessage('must be one of a, b');
        $enum->accept(true, 'w');
    }
}
