<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support\Path;

/**
 * Separator-agnostic path assembly.
 *
 * Two separate concerns get confused under the name "platform agnostic", and only one of them is a
 * real bug:
 *
 * - **Passing a path to PHP.** Windows accepts "/" in every filesystem function, so
 *   `__DIR__ . '/../config'` works everywhere and is not worth rewriting.
 * - **Comparing or slicing a path.** `realpath()`, `SplFileInfo::getRealPath()` and `__DIR__` all
 *   return the *platform's* separator, so a comparison written against a hardcoded "/" silently
 *   fails on Windows -- and fails by reporting that a path is outside a boundary it is inside, which
 *   in a containment check means a security decision made on a string mismatch.
 *
 * This class exists for the second. Input accepts either separator; output is always the platform's.
 *
 * A note on the shape, since Mukora CMS's `DS` constant is the reference: its `switch (PHP_OS)`
 * assigns `DIRECTORY_SEPARATOR` in each named branch and "/" in the default, so every branch already
 * agreed -- and macOS reports "Darwin", which is not one of the named cases, so it took the default.
 * The switch could not change the answer on any platform. What it was reaching for is the rule kept
 * below: never write a separator literal.
 */
final class Path
{
    /** The platform's separator. Named so call sites read as intent rather than as a global. */
    public const string SEPARATOR = DIRECTORY_SEPARATOR;

    /**
     * Join parts with the platform separator, accepting either separator in the input.
     *
     * Empty parts are dropped rather than producing a doubled separator, so an optional trailing
     * segment can be passed straight through.
     */
    public static function join(string ...$parts): string
    {
        $segments = [];
        $absolutePrefix = '';

        foreach ($parts as $index => $part) {
            if ($part === '') {
                continue;
            }

            if ($index === 0) {
                // A leading separator is structural, not a segment -- dropping it would turn an
                // absolute path into a relative one.
                $absolutePrefix = self::leadingSeparator($part);
            }

            foreach (self::segments($part) as $segment) {
                $segments[] = $segment;
            }
        }

        return $absolutePrefix . implode(self::SEPARATOR, $segments);
    }

    /**
     * Rewrite every separator in a path to the platform's.
     */
    public static function normalise(string $path): string
    {
        return self::leadingSeparator($path) . implode(self::SEPARATOR, self::segments($path));
    }

    /**
     * Split a path into its segments, treating "/" and "\" alike and dropping empties.
     *
     * @return list<string>
     */
    public static function segments(string $path): array
    {
        $parts = preg_split('#[/\\\\]+#', $path);

        return array_values(array_filter(
            $parts === false ? [] : $parts,
            static fn (string $segment): bool => $segment !== '',
        ));
    }

    /**
     * Whether `$candidate` is `$boundary` or sits beneath it.
     *
     * Separator-aware on purpose: a plain `str_starts_with` places "/a/bc" inside "/a/b". Both
     * arguments should already be canonical -- this compares strings and resolves nothing.
     */
    public static function isWithin(string $boundary, string $candidate): bool
    {
        $boundary = self::normalise($boundary);
        $candidate = self::normalise($candidate);

        return $candidate === $boundary
            || str_starts_with($candidate, rtrim($boundary, self::SEPARATOR) . self::SEPARATOR);
    }

    /**
     * How many segments deep a path is. Used to check a climb before `dirname()` saturates.
     */
    public static function depth(string $path): int
    {
        return count(self::segments($path));
    }

    private static function leadingSeparator(string $path): string
    {
        return str_starts_with($path, '/') || str_starts_with($path, '\\') ? self::SEPARATOR : '';
    }
}
