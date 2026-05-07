<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Services\Environment;

/**
 * Laravel-compatible .env parser.
 *
 * Supports:
 *   - KEY=value, KEY="value", KEY='value', KEY=
 *   - # comment lines (ignored)
 *   - blank lines (ignored)
 *   - ${VAR} interpolation (referencing keys parsed earlier in the same file)
 *
 * Best-effort: lines that fail to parse are skipped silently (Laravel's
 * own parser is similarly tolerant). Use parseStrict() if you want
 * exceptions on malformed lines.
 */
final class EnvFileParser
{
    /**
     * @return array<string, string> map of KEY => value (last definition wins, matching Laravel).
     */
    public function parse(string $contents): array
    {
        $result = [];

        foreach ($this->lines($contents) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }
            if ($trimmed[0] === '#') {
                continue;
            }

            $eq = strpos($trimmed, '=');
            if ($eq === false) {
                continue;
            }

            $key = trim(substr($trimmed, 0, $eq));
            if (! $this->isValidKey($key)) {
                continue;
            }

            $rawValue = substr($trimmed, $eq + 1);
            $result[$key] = $this->normaliseValue($rawValue, $result);
        }

        return $result;
    }

    /**
     * Returns the keys present in the file, in declaration order
     * (preserves duplicates so writers can know whether a subsequent
     * append would cause a duplicate).
     *
     * @return list<string>
     */
    public function keys(string $contents): array
    {
        $keys = [];

        foreach ($this->lines($contents) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }
            if ($trimmed[0] === '#') {
                continue;
            }
            $eq = strpos($trimmed, '=');
            if ($eq === false) {
                continue;
            }
            $key = trim(substr($trimmed, 0, $eq));
            if ($this->isValidKey($key)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /** @return iterable<string> */
    private function lines(string $contents): iterable
    {
        // Split on either CRLF or LF, preserving empty lines so callers can
        // tell whether the file ends with a newline.
        $lines = preg_split("/\r\n|\n|\r/", $contents);

        return $lines === false ? [] : $lines;
    }

    private function isValidKey(string $key): bool
    {
        if ($key === '') {
            return false;
        }

        return preg_match('/^[A-Za-z_]\w*$/', $key) === 1;
    }

    private function normaliseValue(string $raw, array $resolved): string
    {
        $raw = trim($raw);

        // Strip an inline comment unless inside quotes.
        if (in_array($raw, ['', '""', "''"], true)) {
            return '';
        }

        // Double-quoted: stop at next unescaped " (no inline comment search).
        if (str_starts_with($raw, '"') && preg_match('/^"((?:[^"\\\\]|\\\\.)*)"/', $raw, $m)) {
            return $this->interpolate(stripcslashes($m[1]), $resolved);
        }

        // Single-quoted: literal until the next ' (no interpolation).
        if (str_starts_with($raw, "'") && preg_match("/^'((?:[^'\\\\]|\\\\.)*)'/", $raw, $m)) {
            return $m[1];
        }

        // Unquoted: trim inline comment.
        $hash = strpos($raw, '#');
        if ($hash !== false) {
            $raw = rtrim(substr($raw, 0, $hash));
        }

        return $this->interpolate($raw, $resolved);
    }

    private function interpolate(string $value, array $resolved): string
    {
        // Bounded ${VAR} interpolation. No recursion beyond depth 8 to
        // protect against accidental loops.
        for ($i = 0; $i < 8; $i++) {
            $next = preg_replace_callback(
                '/\$\{([A-Za-z_]\w*)\}/',
                static fn (array $m): string => $resolved[$m[1]] ?? '',
                $value,
            );
            if ($next === null || $next === $value) {
                break;
            }
            $value = $next;
        }

        return $value;
    }
}
