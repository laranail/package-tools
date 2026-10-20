<?php

declare(strict_types=1);

use Simtabi\Laranail\Package\Tools\Testing\CredentialLiterals;

/*
 * The scanner's rules are ported into local copies by repositories that cannot depend on
 * package-tools (notifications, enumerator, simtabi/sis-sdk, simtabi/lacommerce). Each copy
 * declares the RULES_VERSION it mirrors, and scripts/verify-credential-guard-copies.py fails a
 * copy that is behind. That only works if RULES_VERSION moves when a rule does, so this pins
 * the source the version describes.
 *
 * If this fails:
 *  - you changed a rule: set RULES_VERSION to the release you are cutting, update both pins
 *    below, and port the change into every local copy;
 *  - you changed nothing a copy needs (a comment, formatting): update the fingerprint only.
 */
it('moves RULES_VERSION whenever the scanner source changes', function (): void {
    $source = (string) file_get_contents(dirname(__DIR__, 2) . '/src/Testing/CredentialLiterals.php');
    $fingerprint = hash('sha256', (string) preg_replace('/^.*RULES_VERSION = .*\n/m', '', $source));

    expect(CredentialLiterals::RULES_VERSION)->toBe('0.1.10')
        ->and($fingerprint)->toBe('eeabe337494d7e05cd53b29388bafc096dc145d33f579dd9bf10aa579f960658');
});
