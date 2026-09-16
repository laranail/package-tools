<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Commands\Concerns;

/**
 * Normalise console input to the shapes a command's collaborators expect.
 *
 * Symfony's accessors are typed `array|bool|string|null`, and the value that
 * arrives depends on how the option was declared AND how it was written on the
 * command line. Every cast that papers over that has a case where it is wrong,
 * and in each case the command keeps running:
 *
 * - `(string)` on `--connection=` yields `''`, which is not the same as "not
 *   supplied". It is not a usable connection name, cache key or config segment,
 *   so anything keyed on the resolved value silently forks.
 * - `(bool)` on `--flag=false` yields **true**, because `'false'` is a non-empty
 *   string. The flag reads as set precisely when the caller said not to.
 * - `(int)` on a typo yields `0`, which for `--limit`, `--port` or `--timeout`
 *   is a plausible-looking value rather than an error.
 * - `(array)` on a repeatable option keeps empty and untrimmed entries, so a
 *   trailing comma or a stray space becomes a member of the set.
 *
 * These accessors answer each of those once, so sibling commands stop inventing
 * their own answer. The base {@see \Simtabi\Laranail\Package\Tools\Commands\Command}
 * applies this trait, so extending it is enough; `use` the trait directly on a
 * command that already extends something else.
 *
 * Two string accessors are kept, deliberately, because they answer different
 * questions: {@see strOption()} preserves absence and reports it as `null`,
 * while {@see stringOption()} guarantees a `string` by falling back to a
 * caller-supplied default. Reaching for the wrong one is what produced
 * `$this->stringOption('x') !== '' ? $this->stringOption('x') : null` in the
 * wild -- that expression is `strOption()`.
 */
trait ReadsOptions
{
    /**
     * A string option, or null when absent or given without a value.
     *
     * Use when the command must branch on absence -- typically to resolve a
     * default from config or the framework rather than from the signature.
     */
    protected function strOption(string $key): ?string
    {
        $value = $this->option($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * A string option coerced to a string, falling back to `$default`.
     *
     * Use when the command can always proceed. A repeatable option collapses to
     * its first element, so the result is never an "Array to string conversion".
     *
     * `--flag=` falls back to `$default` as well, on the same reasoning as
     * {@see strOption()}: `''` is not a value the caller chose. This is the one
     * behavioural change from the version of this method that lived on the base
     * class, where the default applied only when the option was absent entirely
     * -- so `stringOption('format', 'csv')` answered `''` for `--format=`,
     * which no caller can have wanted. Pass `''` explicitly to keep an empty
     * string.
     */
    protected function stringOption(string $key, string $default = ''): string
    {
        $value = $this->option($key);

        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        if ($value === null || is_bool($value) || $value === '') {
            return $default;
        }

        return (string) $value;
    }

    /**
     * A string argument, or '' when absent.
     */
    protected function strArg(string $key): string
    {
        $value = $this->argument($key);

        return is_string($value) ? $value : '';
    }

    /**
     * A boolean option.
     *
     * A declared flag (`VALUE_NONE`) already arrives as a bool, so the common
     * case is a pass-through. The case this exists for is a flag declared to
     * take a value and then written `--force=false`, where a plain `(bool)`
     * cast returns true: `'false'` is a non-empty string. The recognised false
     * spellings are `false`, `0`, `no` and `off`, per `FILTER_VALIDATE_BOOLEAN`.
     *
     * A value that is neither recognised nor empty counts as true, because the
     * caller did write the flag; `--force=yes` and `--force=sure` both mean the
     * same thing to a reader.
     */
    protected function boolOption(string $key): bool
    {
        $value = $this->option($key);

        if ($value === null) {
            return false;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_array($value)) {
            return $value !== [];
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $parsed ?? true;
    }

    /**
     * An integer option, or `$default` when absent, empty or not numeric.
     *
     * Deliberately not `(int)`, which turns a typo into `0` -- a value that
     * looks deliberate for `--limit`, `--port` or `--timeout` and passes every
     * downstream check.
     */
    protected function intOption(string $key, ?int $default = null): ?int
    {
        $value = $this->strOption($key);

        if ($value === null || ! is_numeric($value)) {
            return $default;
        }

        return (int) $value;
    }

    /**
     * A repeatable option (`--tag=a --tag=b`) as a trimmed, non-empty list.
     *
     * Tolerates the single-value spelling, so a signature that later gains `*`
     * does not change the call site.
     *
     * @return list<string>
     */
    protected function arrayOption(string $key): array
    {
        $value = $this->option($key);

        if ($value === null || is_bool($value)) {
            return [];
        }

        return $this->compact(is_array($value) ? $value : [$value]);
    }

    /**
     * A comma-separated option split into a trimmed, non-empty list.
     *
     * @return list<string>
     */
    protected function listOption(string $key): array
    {
        $value = $this->strOption($key);

        if ($value === null) {
            return [];
        }

        return $this->compact(explode(',', $value));
    }

    /**
     * Trim, drop anything that is not a non-empty string, and re-index.
     *
     * @param array<array-key, mixed> $values
     *
     * @return list<string>
     */
    private function compact(array $values): array
    {
        $strings = array_map(
            static fn (mixed $value): string => is_scalar($value) ? trim((string) $value) : '',
            $values,
        );

        return array_values(array_filter($strings, static fn (string $value): bool => $value !== ''));
    }
}
