<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/*
 * scripts/verify-release-publish.py: no workflow may leave a GitHub release as a draft.
 *
 * Deleting a tag reverts its release to a draft, and `gh release edit` leaves that flag exactly as
 * it finds it, so an edit after a tag is deleted and re-pushed republishes nothing on a run that
 * still goes green. Four hand-rolled call sites carried that defect across three repositories.
 *
 * The check is unusual in that MOST repositories are clean by having no `gh release edit` at all --
 * they use softprops/action-gh-release, which publishes an existing draft on its own. So "found
 * nothing" is the normal pass, and a matcher that silently stopped matching would report the whole
 * estate clean. That is why the script runs built-in controls every time, and why the last test
 * here sabotages the matcher in a copy of the script and demands the controls catch it.
 */
function runReleasePublish(string $workflow, string $name = 'release.yml', ?string $script = null): Process
{
    $root = dirname(__DIR__, 2);
    $repo = sys_get_temp_dir() . '/release-publish-' . bin2hex(random_bytes(6));
    mkdir($repo . '/.github/workflows', 0o755, true);

    if ($workflow !== '') {
        file_put_contents($repo . '/.github/workflows/' . $name, $workflow);
    }

    $process = new Process(['python3', $script ?? $root . '/scripts/verify-release-publish.py', $repo]);
    $process->run();

    return $process;
}

function releaseStep(string ...$lines): string
{
    return "jobs:\n  release:\n    steps:\n      - run: |\n" . implode('', array_map(
        static fn (string $line): string => "          {$line}\n",
        $lines,
    ));
}

it('fails an edit that does not publish the release', function (): void {
    $process = runReleasePublish(releaseStep('gh release edit "$TAG" --notes-file body.md'));

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput())->toContain('DRAFT     release.yml')
        ->toContain('add --draft=false')
        ->toContain('1 of 1 release edit(s) would leave a draft behind');
})->skipOnWindows();

it('passes an edit that publishes the release', function (): void {
    $process = runReleasePublish(releaseStep('gh release edit "$TAG" --draft=false --notes-file body.md'));

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('1 release edit(s): every one publishes');
})->skipOnWindows();

it('joins a shell line continuation, so a flag on the next line still counts', function (): void {
    // Matching line by line would call this a failure and send someone to add a flag already there.
    $process = runReleasePublish(releaseStep(
        'gh release edit "$TAG" \\',
        '  --draft=false \\',
        '  --notes-file body.md',
    ));

    expect($process->getExitCode())->toBe(0);
})->skipOnWindows();

it('does not read prose about the command as the command', function (): void {
    // Every repository that carried this defect also carried a comment naming `gh release edit`.
    $process = runReleasePublish(releaseStep(
        '# `gh release edit` takes no assets, so the upload below is explicit',
        'gh release edit "$TAG" --draft=false --notes-file body.md',
    ));

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('1 release edit(s)');
})->skipOnWindows();

it('leaves other gh subcommands alone', function (): void {
    $process = runReleasePublish(releaseStep(
        'gh release create "$TAG" --notes-file body.md',
        'gh release upload "$TAG" sbom.cdx.json --clobber',
        'gh release view "$TAG" --json isDraft',
    ));

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('no hand-rolled release edit');
})->skipOnWindows();

it('passes a repository that edits no release, which is most of them', function (): void {
    // softprops/action-gh-release publishes an existing draft unless told to keep it, so a
    // repository using it has nothing to fix. Finding nothing is the normal pass, not a warning.
    $process = runReleasePublish("jobs:\n  release:\n    steps:\n      - uses: softprops/action-gh-release@v2\n");

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('no hand-rolled release edit');
})->skipOnWindows();

it('reads a .yaml workflow, which globbing *.yml silently skipped', function (): void {
    $process = runReleasePublish(
        releaseStep('gh release edit "$TAG" --notes-file body.md'),
        'release.yaml',
    );

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput())->toContain('release.yaml');
})->skipOnWindows();

it('refuses to report a clean repository when the workflows directory is empty', function (): void {
    // With "no edits found" as the normal pass, an empty or unmatched directory would otherwise be
    // indistinguishable from a repository that is genuinely clean.
    $process = runReleasePublish('');

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput())->toContain('Refusing to report a clean repository');
})->skipOnWindows();

it('counts every workflow file, not only the ones holding a command', function (): void {
    $root = dirname(__DIR__, 2);
    $repo = sys_get_temp_dir() . '/release-publish-' . bin2hex(random_bytes(6));
    mkdir($repo . '/.github/workflows', 0o755, true);
    file_put_contents($repo . '/.github/workflows/ci.yml', "jobs:\n  a:\n    steps: []\n");
    file_put_contents(
        $repo . '/.github/workflows/release.yml',
        releaseStep('gh release edit "$TAG" --draft=false --notes-file body.md'),
    );

    $process = new Process(['python3', $root . '/scripts/verify-release-publish.py', $repo]);
    $process->run();

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('2 workflow files, 1 release edit(s)');
})->skipOnWindows();

it('fails on its own controls when the matcher stops matching', function (): void {
    // The one way this check can lie: most repositories pass by having no command at all, so a
    // broken matcher would report the estate clean. Sabotage the pattern in a copy and the controls
    // must refuse to report anything -- and must say the matcher, not the repository, is at fault.
    $root = dirname(__DIR__, 2);
    $sabotaged = sys_get_temp_dir() . '/verify-release-publish-broken-' . bin2hex(random_bytes(6)) . '.py';
    $source = (string) file_get_contents($root . '/scripts/verify-release-publish.py');
    $broken = str_replace('r"gh release edit[^\n]*"', 'r"gh release editx[^\n]*"', $source);

    expect($broken)->not->toBe($source, 'the sabotage must actually change the pattern');

    file_put_contents($sabotaged, $broken);

    $process = runReleasePublish(
        releaseStep('gh release edit "$TAG" --notes-file body.md'),
        'release.yml',
        $sabotaged,
    );

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput())->toContain('The matcher is broken')
        ->toContain('CONTROL')
        ->and($process->getOutput())->not->toContain('DRAFT     release.yml');
})->skipOnWindows();
