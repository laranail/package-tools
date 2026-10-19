<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Testing;

use PHPUnit\Framework\Assert;

/**
 * Keeps literal credentials out of a test suite, so secret scanning has nothing to report.
 *
 * A secret scanner cannot tell a test password from a leaked one, and reports both. It matches on
 * shape -- a long string mixing case, digits and symbols -- and on name: any literal assigned to
 * something called `password`, `secret` or `token`, however weak, and the password in a URL such
 * as `mysql://admin:…@db.example.com`. GitGuardian raised 28 incidents across laranail by
 * 2026-10-18, and 27 of them were test literals or false positives, every one closed by hand.
 *
 * Tests take their credentials from {@see TestCredentials} (or the package's own generator)
 * instead, and this assertion fails on any literal left behind:
 *
 * ```php
 * uses(AssertsNoLiteralCredentials::class);
 *
 * it('keeps literal credentials out of the tests', function (): void {
 *     $this->assertNoLiteralCredentialsIn(dirname(__DIR__), minFiles: 20);
 * });
 * ```
 *
 * A value a test must hold exactly -- an RFC test vector, a public key whose fingerprint is
 * asserted, a weak input fed to a strength rule -- stays, marked on its line or the line above
 * with {@see CREDENTIAL_LITERAL_EXEMPTION} and a reason. Keep such values out of credential-named positions where
 * the test allows it (`RFC7677_SALT`, not `ADMIN_PASSWORD`).
 *
 * Scans PHP source only. Credentials in `phpunit.xml`, `.env.testing` or a workflow are outside it.
 */
trait AssertsNoLiteralCredentials
{
    /**
     * Mark a line (or the line above it) holding a deliberate fixed value, with a reason:
     * `// @credential-literal-exempt: RFC 7677 test vector`.
     */
    public const string CREDENTIAL_LITERAL_EXEMPTION = CredentialLiterals::EXEMPTION;

    /**
     * Assert that no PHP file under $dirs holds a literal credential. What counts as one is
     * {@see CredentialLiterals::in()}.
     *
     * @param string|list<string> $dirs Test directories to scan, e.g. `tests/` and
     *                                  `database/factories/`.
     * @param int $minFiles The fewest PHP files the scan may inspect. A scan that finds fewer --
     *                      a path typo, a renamed directory -- fails rather than reporting a
     *                      clean suite.
     * @param list<string> $exemptFiles Basenames to skip. Prefer the line marker
     *                                  {@see CREDENTIAL_LITERAL_EXEMPTION}: a file-wide skip also
     *                                  covers literals added to that file later.
     */
    protected function assertNoLiteralCredentialsIn(string|array $dirs, int $minFiles = 1, array $exemptFiles = []): void
    {
        $dirs = (array) $dirs;
        foreach ($dirs as $dir) {
            Assert::assertDirectoryExists($dir);
        }

        [$scanned, $offenders] = CredentialLiterals::scan($dirs, $exemptFiles);

        Assert::assertGreaterThanOrEqual($minFiles, $scanned, sprintf(
            'Only %d PHP file(s) were scanned, fewer than the floor of %d, so this assertion proved '
            . 'little. Check the directories passed to it.',
            $scanned,
            $minFiles,
        ));

        Assert::assertSame([], $offenders, sprintf(
            'These tests hold a literal credential, which secret scanning reports as a leak. Generate it '
            . "with TestCredentials (or the package's own generator), or mark a deliberate fixed value "
            . "with %s and a reason:\n  %s",
            self::CREDENTIAL_LITERAL_EXEMPTION,
            implode("\n  ", $offenders),
        ));
    }

    /**
     * Assert that every purpose passed to {@see TestCredentials} under $dirs is a single plain
     * lowercase word. Opt in alongside {@see assertNoLiteralCredentialsIn()}: GitGuardian reads a
     * compound purpose such as `'smtp-new'` as the password itself. What counts is
     * {@see CredentialLiterals::purposesIn()}.
     *
     * @param string|list<string> $dirs
     */
    protected function assertCredentialPurposesAreWordsIn(string|array $dirs, int $minFiles = 1): void
    {
        $dirs = (array) $dirs;
        foreach ($dirs as $dir) {
            Assert::assertDirectoryExists($dir);
        }

        [$scanned, $offenders] = CredentialLiterals::scanPurposes($dirs);

        Assert::assertGreaterThanOrEqual($minFiles, $scanned, sprintf(
            'Only %d PHP file(s) were scanned, fewer than the floor of %d, so this assertion proved '
            . 'little. Check the directories passed to it.',
            $scanned,
            $minFiles,
        ));

        Assert::assertSame([], $offenders, sprintf(
            'These TestCredentials purposes are not single lowercase words, and secret scanning reads a '
            . "compound one as the password itself. Use one plain word ('user', 'wrong', 'renewed'), or "
            . "no purpose where the value need not differ from another:\n  %s",
            implode("\n  ", $offenders),
        ));
    }
}
