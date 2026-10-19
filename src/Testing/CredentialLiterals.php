<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Testing;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

/**
 * Finds literal credentials in PHP source: the scanner behind {@see AssertsNoLiteralCredentials}.
 *
 * A secret scanner reports a literal by shape (a long string mixing case, digits and symbols) and
 * by name (any literal under `password`, `secret` or `token`, and the password in a URL), so
 * this reports both.
 */
final class CredentialLiterals
{
    /**
     * Mark a line (or the line above it) holding a deliberate fixed value, with a reason:
     * `// @credential-literal-exempt: RFC 7677 test vector`.
     */
    public const string EXEMPTION = '@credential-literal-exempt';

    /**
     * Every literal credential in $source, as [byte offset, rule, literal].
     *
     * Rules:
     *  - `named`: a literal of any strength in a position named for a credential -- an array key,
     *    a variable, a property, a constant, `define()`, a named argument, or a `set()` /
     *    `assertSet()` / `Config::set()` key.
     *  - `strong`: a strong-looking literal passed to `new Password(...)`.
     *  - `url`: the password in a URL's userinfo.
     *
     * Not a credential, and left alone: the empty string, prose (anything with whitespace),
     * validation rules (anything with `|`), and interpolated strings.
     *
     * @return list<array{int, string, string}>
     */
    public static function in(string $source): array
    {
        $name = '[\w.-]*(?:password|passwd|secret|token|api_?key|private_?key)[\w.-]*';
        $literal = '(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)")';

        $named = [
            // 'password' => '…' (the key may be dotted: 'services.stripe.secret')
            '/[\'"]' . $name . '[\'"]\s*=>\s*' . $literal . '/i',
            // $password = '…', $this->token = '…', and a property default
            '/\$(?:this->)?' . $name . '\s*=\s*' . $literal . '/i',
            // const ADMIN_SECRET = '…', typed or not
            '/\bconst\s+(?:\??\w+\s+)?' . $name . '\s*=\s*' . $literal . '/i',
            // define('LIVE_TOKEN', '…')
            '/\bdefine\(\s*[\'"]' . $name . '[\'"]\s*,\s*' . $literal . '/i',
            // a named argument: f(password: '…')
            '/[(,]\s*' . $name . '\s*:\s*' . $literal . '/i',
            // ->set('password', '…'), ->assertSet(…), Config::set('services.x.secret', …)
            '/(?:->|::)(?:set|assertSet)\(\s*[\'"]' . $name . '[\'"]\s*,\s*' . $literal . '/i',
        ];

        $found = [];

        foreach ($named as $pattern) {
            foreach (self::literalMatches($pattern, $source) as [$offset, $value]) {
                if (self::isCredentialShaped($value)) {
                    $found[] = [$offset, 'named', $value];
                }
            }
        }

        foreach (self::literalMatches('/\bnew\s+(?:\\\\?[\w\\\\]*\\\\)?Password\(\s*' . $literal . '/', $source) as [$offset, $value]) {
            if (self::looksLikeAStrongPassword($value)) {
                $found[] = [$offset, 'strong', $value];
            }
        }

        // scheme://user:password@host, inside any literal.
        preg_match_all('#\b[a-z][a-z0-9+.-]*://[^\s:/@\'"]+:([^\s@/\'"$%{]+)@#i', $source, $urls, PREG_OFFSET_CAPTURE);
        foreach ($urls[1] as [$value, $offset]) {
            $found[] = [$offset, 'url', $value];
        }

        usort($found, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return $found;
    }

    /**
     * Twelve or more characters mixing upper case, lower case, a digit and a symbol, and not a
     * path, namespace, URL or identifier -- the scanners' own heuristic.
     */
    public static function looksLikeAStrongPassword(string $value): bool
    {
        return strlen($value) >= 12
            && preg_match('/[A-Z]/', $value) === 1
            && preg_match('/[a-z]/', $value) === 1
            && preg_match('/\d/', $value) === 1
            && preg_match('/[^A-Za-z0-9\s]/', $value) === 1
            && preg_match('/\s/', $value) === 0
            && preg_match('#^[\\\\/]|\\\\|://|^[\w.-]+$#', $value) === 0;
    }

    /**
     * Scan every PHP file under $dirs.
     *
     * @param list<string> $dirs
     * @param list<string> $exemptFiles basenames to skip
     *
     * @return array{int, list<string>} the number of files scanned, and one line per literal:
     *                                  `path:line (rule) xx…`, never the whole value
     */
    public static function scan(array $dirs, array $exemptFiles = []): array
    {
        $scanned = 0;
        $offenders = [];

        foreach ($dirs as $dir) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
                if ($file->getExtension() !== 'php' || in_array($file->getFilename(), $exemptFiles, true)) {
                    continue;
                }

                $scanned++;
                $source = (string) file_get_contents($file->getPathname());
                $lines = explode("\n", $source);

                foreach (self::in($source) as [$offset, $rule, $value]) {
                    $line = substr_count($source, "\n", 0, $offset) + 1;

                    if (self::isExempt($lines, $line)) {
                        continue;
                    }

                    // Never the value itself: the message lands in CI logs.
                    $offenders[] = sprintf('%s:%d (%s) %s…', $file->getPathname(), $line, $rule, substr($value, 0, 2));
                }
            }
        }

        return [$scanned, $offenders];
    }

    /**
     * @return list<array{int, string}> [offset, value] of each quoted literal the pattern captured
     */
    private static function literalMatches(string $pattern, string $source): array
    {
        preg_match_all($pattern, $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL);

        $found = [];
        foreach ($matches as $match) {
            // Group 1 is the single-quoted body, group 2 the double-quoted one.
            $double = $match[2][0] !== null;
            [$value, $offset] = $double ? $match[2] : $match[1];

            if ($double && preg_match('/(?<!\\\\)\$/', (string) $value) === 1) {
                continue; // interpolated: the value comes from code
            }

            $found[] = [(int) $offset, (string) $value];
        }

        return $found;
    }

    private static function isCredentialShaped(string $value): bool
    {
        return $value !== ''
            && preg_match('/\s/', $value) === 0
            && ! str_contains($value, '|');
    }

    /**
     * @param list<string> $lines
     */
    private static function isExempt(array $lines, int $line): bool
    {
        return str_contains($lines[$line - 1] ?? '', self::EXEMPTION)
            || str_contains($lines[$line - 2] ?? '', self::EXEMPTION);
    }
}
