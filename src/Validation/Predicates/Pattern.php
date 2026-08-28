<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Validation\Predicates;

/**
 * Canonical patterns, and one total way to test a value against them.
 *
 * This exists because the same three-line check was written independently in
 * a dozen packages, and most of the copies shared a defect: they called
 * `preg_match()` on a `mixed` value with no `is_string()` guard. Passing an
 * array or an int to `preg_match()` raises a `TypeError`, so a validator whose
 * whole job is to *reject* bad input instead crashed on it. Six validators in
 * `laranail/console` shipped that bug until it was fixed there; the rest of the
 * family still has it.
 *
 * {@see self::matches()} is total by construction: every non-string is a clean
 * `false`, never an exception. Build on it rather than calling `preg_match()`
 * directly, and that class of bug cannot come back.
 *
 * Deliberately framework-free -- no Illuminate, no container, no translation.
 * That is what lets `laranail/console` (PHP ^8.4.1) and `laranail/validation`
 * (PHP ^8.5) both consume it without either depending on the other, which they
 * cannot do: validation already requires console, so the reverse edge would be
 * a cycle.
 *
 * ## Scope
 *
 * These are *primitives* -- fixed patterns with no configuration. Richer rules
 * belong in `laranail/validation`, which offers a configurable `Username`
 * (min, max, separators, reserved list), a `PersonName` that counts names and
 * exports client-side rules, and a `CssColor` covering hex 3/4/6/8, rgb, hsl,
 * hsv and named colours. Nothing here should grow options; when a caller needs
 * options, it needs validation's rule instead.
 */
final class Pattern
{
    /**
     * ASCII letters only.
     *
     * Deliberately not `\p{L}`: this is the pattern the family has always used
     * for "alpha", and widening it to Unicode would silently start accepting
     * input that previously failed. {@see self::PERSON_NAME} is the Unicode-aware
     * one, because a person's name is exactly where that matters.
     */
    public const string ALPHA = '/^[a-zA-Z]+$/';

    /** ASCII letters and digits. */
    public const string ALPHANUMERIC = '/^[a-zA-Z0-9]+$/';

    /**
     * RFC 4122 UUID, versions 1 through 5.
     *
     * The version nibble is `[1-5]`, not `[4]`. An earlier copy of this pattern
     * accepted v4 only while sitting next to an enum that advertised v1, v3, v4
     * and v5 -- so the code rejected three of the four types it documented.
     */
    public const string UUID = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/';

    /** Lowercase, hyphen-separated slug with no leading, trailing or doubled hyphen. */
    public const string SLUG = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** `#rgb` or `#rrggbb`. For the full CSS colour surface use validation's `CssColor`. */
    public const string HEX_COLOUR = '/^#([a-fA-F0-9]{3}|[a-fA-F0-9]{6})$/';

    /**
     * A loose international phone shape: optional `+`, then 10-15 digits.
     *
     * Shape only. It does not know which prefixes exist, so it accepts numbers
     * that cannot be dialled. Where correctness matters, use validation's
     * `Rules\Telecom\Phone`, which is backed by `laranail/phone`.
     */
    public const string E164_SHAPE = '/^\+?\d{10,15}$/';

    /**
     * Word characters, 3 to 20.
     *
     * The simple form, for a CLI prompt. Validation's `Rules\Text\Username`
     * takes min, max, allowed separators, a lowercase flag and a reserved-name
     * list; prefer it anywhere those matter.
     */
    public const string USERNAME_SIMPLE = '/^\w{3,20}$/';

    /** Unicode letters, spaces, apostrophes and hyphens. */
    public const string PERSON_NAME = '/^[\p{L} \'-]+$/u';

    /**
     * Test a value against a pattern. A non-string is `false`, not a `TypeError`.
     *
     * The guarantee covers the *value*, which is untrusted input. It does not
     * cover the *pattern*, which is a constant written by a developer: a
     * malformed one still emits PHP's warning, and that is deliberate. Silently
     * swallowing it would turn a typo in a regex into a rule that quietly
     * rejects everything, which is far harder to notice than a warning.
     */
    public static function matches(string $pattern, mixed $value): bool
    {
        return is_string($value) && preg_match($pattern, $value) === 1;
    }

    /** ASCII letters only. */
    public static function alpha(mixed $value): bool
    {
        return self::matches(self::ALPHA, $value);
    }

    /** ASCII letters and digits. */
    public static function alphanumeric(mixed $value): bool
    {
        return self::matches(self::ALPHANUMERIC, $value);
    }

    /** RFC 4122 UUID, versions 1 through 5. */
    public static function uuid(mixed $value): bool
    {
        return self::matches(self::UUID, $value);
    }

    /** Lowercase hyphen-separated slug. */
    public static function slug(mixed $value): bool
    {
        return self::matches(self::SLUG, $value);
    }

    /** `#rgb` or `#rrggbb`. */
    public static function hexColour(mixed $value): bool
    {
        return self::matches(self::HEX_COLOUR, $value);
    }

    /** Optional `+` then 10-15 digits. Shape only. */
    public static function e164Shape(mixed $value): bool
    {
        return self::matches(self::E164_SHAPE, $value);
    }

    /** Word characters, 3 to 20. */
    public static function usernameSimple(mixed $value): bool
    {
        return self::matches(self::USERNAME_SIMPLE, $value);
    }

    /** Unicode letters, spaces, apostrophes and hyphens. */
    public static function personName(mixed $value): bool
    {
        return self::matches(self::PERSON_NAME, $value);
    }
}
