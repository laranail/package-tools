<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Testing;

use InvalidArgumentException;

/**
 * Credentials for tests, generated when the suite runs instead of written into the source.
 *
 * A literal password, key or token in a test is indistinguishable from a leaked one to a secret
 * scanner, and the scanners do not try to tell them apart: GitGuardian raised 28 incidents across
 * laranail by 2026-10-18, 27 of them test literals, each closed by hand. A value generated here
 * never reaches the repository, so there is nothing to report.
 *
 * Every value is memoised per purpose for the life of the process, so all assertions in one run
 * agree, and differs from run to run. Ask for a different purpose when two values must differ:
 *
 * ```php
 * $theirs = TestCredentials::password('existing-account');
 * $ours   = TestCredentials::password('new-account');
 * ```
 *
 * The guard that keeps literals out is {@see AssertsNoLiteralCredentials}.
 */
final class TestCredentials
{
    /**
     * Symbols that survive a DSN, a URL's userinfo, a shell and a quoted string unescaped. No
     * quote, backslash, `$`, `@`, `:`, `/`, `#`, `%`, `?` or `+`.
     */
    private const string SYMBOLS = '!*-=^_~';

    /**
     * The symbols that are not identifier characters. The one symbol a password is guaranteed is
     * drawn from these: a password whose only symbols were `-` and `_` would read as an identifier
     * (`^[\w.-]+$`), and secret scanners -- and {@see CredentialLiterals::looksLikeAStrongPassword()}
     * -- do not treat that as a strong password.
     */
    private const string NON_WORD_SYMBOLS = '!*=^~';

    private const string UPPER = 'ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const string LOWER = 'abcdefghijkmnopqrstuvwxyz';

    private const string DIGITS = '23456789';

    /** @var array<string, string> */
    private static array $memo = [];

    /**
     * A strong password: upper and lower case, a digit and a symbol, at least once each.
     *
     * @param int<4, max> $length
     */
    public static function password(string $purpose = 'default', int $length = 18): string
    {
        if ($length < 4) {
            throw new InvalidArgumentException('A password needs at least 4 characters, one of each class.');
        }

        return self::$memo["password:{$purpose}:{$length}"] ??= self::generatePassword($length);
    }

    /**
     * An opaque secret or token, as lower-case hex.
     *
     * @param int<1, max> $bytes
     */
    public static function secret(string $purpose = 'default', int $bytes = 16): string
    {
        return self::$memo["secret:{$purpose}:{$bytes}"] ??= bin2hex(self::bytes($bytes));
    }

    /**
     * Raw key material, e.g. a data key to encrypt or wrap.
     *
     * @param int<1, max> $bytes
     */
    public static function key(string $purpose = 'default', int $bytes = 32): string
    {
        return self::$memo["key:{$purpose}:{$bytes}"] ??= self::bytes($bytes);
    }

    private static function generatePassword(int $length): string
    {
        $all = self::UPPER . self::LOWER . self::DIGITS . self::SYMBOLS;
        $characters = [
            self::pick(self::UPPER),
            self::pick(self::LOWER),
            self::pick(self::DIGITS),
            self::pick(self::NON_WORD_SYMBOLS),
        ];

        while (count($characters) < $length) {
            $characters[] = self::pick($all);
        }

        // Fisher-Yates with a CSPRNG, so the guaranteed classes do not sit at fixed positions.
        for ($i = count($characters) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$characters[$i], $characters[$j]] = [$characters[$j], $characters[$i]];
        }

        return implode('', $characters);
    }

    private static function pick(string $alphabet): string
    {
        return $alphabet[random_int(0, strlen($alphabet) - 1)];
    }

    private static function bytes(int $bytes): string
    {
        if ($bytes < 1) {
            throw new InvalidArgumentException('Ask for at least one byte.');
        }

        return random_bytes($bytes);
    }
}
