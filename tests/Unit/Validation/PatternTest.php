<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Validation;

use stdClass;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use Simtabi\Laranail\Package\Tools\Validation\Predicates\Pattern;

/**
 * The shared predicates, and the crash they exist to prevent.
 */
final class PatternTest extends TestCase
{
    /**
     * The reason this class exists.
     *
     * `preg_match()` raises a TypeError on a non-string subject, so a validator
     * calling it unguarded crashes on exactly the input it is meant to reject.
     * Every predicate must answer false instead -- for every non-string shape,
     * not just null.
     *
     * @return iterable<string, array{mixed}>
     */
    public static function nonStrings(): iterable
    {
        yield 'null' => [null];
        yield 'int' => [42];
        yield 'float' => [1.5];
        yield 'true' => [true];
        yield 'false' => [false];
        yield 'array' => [['a']];
        yield 'object' => [new stdClass];
    }

    #[Test]
    #[DataProvider('nonStrings')]
    public function every_predicate_is_total_on_non_string_input(mixed $value): void
    {
        foreach (['alpha', 'alphanumeric', 'uuid', 'slug', 'hexColour', 'e164Shape', 'usernameSimple', 'personName'] as $method) {
            $this->assertFalse(
                Pattern::{$method}($value),
                "Pattern::{$method}() must answer false for a non-string, not throw",
            );
        }
    }

    #[Test]
    public function uuid_accepts_versions_one_through_five(): void
    {
        // The bug this replaces accepted v4 only, while sitting beside an enum
        // advertising v1, v3, v4 and v5.
        foreach ([1, 2, 3, 4, 5] as $version) {
            $this->assertTrue(
                Pattern::uuid("6ba7b810-9dad-{$version}1d1-80b4-00c04fd430c8"),
                "UUID version {$version} should be accepted",
            );
        }
    }

    #[Test]
    public function uuid_rejects_a_bad_version_or_variant_nibble(): void
    {
        $this->assertFalse(Pattern::uuid('6ba7b810-9dad-61d1-80b4-00c04fd430c8'), 'version 6');
        $this->assertFalse(Pattern::uuid('6ba7b810-9dad-41d1-70b4-00c04fd430c8'), 'variant 7');
        $this->assertFalse(Pattern::uuid('not-a-uuid'));
    }

    #[Test]
    public function alpha_is_ascii_only_and_not_unicode(): void
    {
        $this->assertTrue(Pattern::alpha('abcXYZ'));
        $this->assertFalse(Pattern::alpha('abc1'));
        $this->assertFalse(Pattern::alpha(''), 'empty is not one-or-more');
        // Widening this to \p{L} would silently start accepting input that has
        // always failed, so it is asserted rather than left to chance.
        $this->assertFalse(Pattern::alpha('Zoë'));
    }

    #[Test]
    public function person_name_is_unicode_aware(): void
    {
        $this->assertTrue(Pattern::personName('Zoë'));
        $this->assertTrue(Pattern::personName("O'Brien"));
        $this->assertTrue(Pattern::personName('Jean-Luc Picard'));
        $this->assertFalse(Pattern::personName('R2D2'));
    }

    #[Test]
    public function slug_rejects_leading_trailing_and_doubled_hyphens(): void
    {
        $this->assertTrue(Pattern::slug('a-good-slug'));
        $this->assertTrue(Pattern::slug('single'));
        $this->assertFalse(Pattern::slug('-leading'));
        $this->assertFalse(Pattern::slug('trailing-'));
        $this->assertFalse(Pattern::slug('double--hyphen'));
        $this->assertFalse(Pattern::slug('Upper-Case'));
    }

    #[Test]
    public function hex_colour_accepts_three_and_six_digit_forms(): void
    {
        $this->assertTrue(Pattern::hexColour('#fff'));
        $this->assertTrue(Pattern::hexColour('#FFAA33'));
        $this->assertFalse(Pattern::hexColour('fff'), 'the hash is required');
        $this->assertFalse(Pattern::hexColour('#ffff'), 'four digits is the 8-digit CSS form, not this one');
    }

    #[Test]
    public function e164_shape_checks_shape_only(): void
    {
        $this->assertTrue(Pattern::e164Shape('+12025550123'));
        $this->assertTrue(Pattern::e164Shape('12025550123'));
        $this->assertFalse(Pattern::e164Shape('+1202555'), 'under ten digits');
        $this->assertFalse(Pattern::e164Shape('+1 202 555 0123'), 'spaces are not stripped here');
    }

    #[Test]
    public function username_simple_enforces_its_length_bounds(): void
    {
        $this->assertTrue(Pattern::usernameSimple('abc'));
        $this->assertTrue(Pattern::usernameSimple(str_repeat('a', 20)));
        $this->assertFalse(Pattern::usernameSimple('ab'), 'below three');
        $this->assertFalse(Pattern::usernameSimple(str_repeat('a', 21)), 'above twenty');
        $this->assertFalse(Pattern::usernameSimple('has space'));
    }
}
