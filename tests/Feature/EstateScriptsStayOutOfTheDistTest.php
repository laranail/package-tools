<?php

declare(strict_types=1);

use Simtabi\Laranail\Package\Tools\Support\Dist\GitRevisionReader;

/*
 * `scripts/` is repository tooling, and a dist install must not receive it.
 *
 * ## The bug this exists for
 *
 * `.gitattributes` carried `/.scripts export-ignore` -- a real directory holding one file --
 * while `scripts/`, with eleven tracked files, was never excluded. So every
 * `composer require laranail/package-tools` delivered Simtabi's GitHub administration scripts
 * into the consumer's `vendor/`. Measured 2026-11-18: 12 of the archive's 393 entries, landing
 * in 68 laranail packages' vendor trees.
 *
 * Nothing consumed them from there. CI runs these checks by checking the repository out beside
 * the caller (`laranail/.github`'s `hygiene.yml`, `repository: laranail/package-tools`), which
 * `export-ignore` does not affect -- it governs `git archive`, not a clone. The shipping was
 * incidental the whole time.
 *
 * "Internal by convention" is exactly what let it happen, so this is a test rather than a comment.
 *
 * ## Why it asserts in both directions
 *
 * Excluding a directory and deleting it look identical from inside the archive. A test that only
 * checked "no scripts/ in the dist" would pass just as happily if someone removed the scripts
 * altogether -- reporting success for the loss of the tooling it exists to protect. So the
 * tracked tree is asserted too, with a floor.
 */

/** The reader takes archive and tracked paths from one revision, which is the committed state. */
function distReader(): GitRevisionReader
{
    return new GitRevisionReader(dirname(__DIR__, 2));
}

it('ships no scripts/ entry to a dist install', function (): void {
    $archived = distReader()->archivedPaths('HEAD');

    // A reader that returned nothing would make every assertion below vacuous.
    expect($archived)->not->toBeEmpty()
        ->and(count($archived))->toBeGreaterThan(200);

    $scripts = array_values(array_filter(
        $archived,
        static fn (string $path): bool => str_starts_with($path, 'scripts/'),
    ));

    expect($scripts)->toBe([], sprintf(
        'These are repository tooling and must not reach a consumer vendor/ tree: %s. '
        . 'Add the path to .gitattributes as export-ignore.',
        implode(', ', $scripts),
    ));
});

it('still tracks the scripts, so exclusion is not confused with deletion', function (): void {
    $tracked = array_values(array_filter(
        distReader()->trackedPaths('HEAD'),
        static fn (string $path): bool => str_starts_with($path, 'scripts/'),
    ));

    // Eleven when written. The floor catches a quiet deletion without pinning the
    // exact set, which would fail on every legitimate addition.
    expect(count($tracked))->toBeGreaterThanOrEqual(10);
});

it('keeps the composer manifest from promising anything under scripts/', function (): void {
    $manifest = json_decode(distReader()->manifest('HEAD'), true, 512, JSON_THROW_ON_ERROR);

    // The inverse of DistIntegrityAuditor: that asserts every promised path survives the
    // archive; this asserts the manifest promises nothing from a path the archive now strips.
    // Without it, excluding scripts/ could break a consumer through the manifest instead.
    $promises = json_encode($manifest, JSON_THROW_ON_ERROR);

    expect($promises)->not->toContain('"scripts/')
        ->and($promises)->not->toContain('/scripts/');
});
