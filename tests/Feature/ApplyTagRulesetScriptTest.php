<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/*
 * scripts/apply-tag-ruleset.py: published version tags cannot be moved or deleted.
 *
 * A fake `gh` on PATH answers the rulesets listing with whatever the test needs and records every
 * call, so the create-or-update decision and the 403 path are checked without touching GitHub.
 */
function runTagRuleset(array $args, string $listing = '[]', int $listingExit = 0): array
{
    $root = dirname(__DIR__, 2);
    $bin = sys_get_temp_dir() . '/tag-ruleset-' . bin2hex(random_bytes(6));
    mkdir($bin, 0o755, true);
    $log = $bin . '/calls.log';

    file_put_contents($bin . '/listing.json', $listing);
    file_put_contents($bin . '/gh', sprintf(<<<'SH'
        #!/usr/bin/env bash
        echo "$*" >> %1$s
        if [ "$1 $2" = "api repos/acme/widget/rulesets?includes_parents=false" ]; then
          if [ %2$d -ne 0 ]; then echo "HTTP 403: Upgrade to GitHub Pro" >&2; exit 1; fi
          cat %3$s; exit 0
        fi
        cat > /dev/null
        echo '{"id": 4242}'
        SH, escapeshellarg($log), $listingExit, escapeshellarg($bin . '/listing.json')));
    chmod($bin . '/gh', 0o755);

    $process = new Process(['python3', $root . '/scripts/apply-tag-ruleset.py', ...$args], null, ['PATH' => $bin . ':' . getenv('PATH')]);
    $process->run();

    return [$process, is_file($log) ? (string) file_get_contents($log) : ''];
}

it('prints the payload on a dry run and calls GitHub not at all', function (): void {
    [$process, $calls] = runTagRuleset(['acme/widget']);

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('Dry run.')
        ->toContain('"target": "tag"')
        ->toContain('refs/tags/v*')
        ->toContain('refs/tags/src/v*')
        ->toContain('"type": "deletion"')
        ->toContain('"type": "update"')
        ->and($calls)->toBe('');
})->skipOnWindows();

it('creates the ruleset when the repository has none', function (): void {
    [$process, $calls] = runTagRuleset(['acme/widget', '--apply']);

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('created tag ruleset 4242')
        ->and($calls)->toContain('api -X POST repos/acme/widget/rulesets --input -');
})->skipOnWindows();

it('updates the existing ruleset in place instead of adding a second one', function (): void {
    $listing = '[{"id": 7, "name": "Protect release tags", "target": "tag"}, {"id": 8, "name": "Protect release tags", "target": "branch"}]';
    [$process, $calls] = runTagRuleset(['acme/widget', '--apply'], $listing);

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('updated tag ruleset 4242 in place')
        ->and($calls)->toContain('api -X PUT repos/acme/widget/rulesets/7 --input -')
        ->not->toContain('-X POST');
})->skipOnWindows();

it('says rulesets are unavailable rather than failing on a free-plan private repository', function (): void {
    [$process] = runTagRuleset(['acme/widget', '--apply'], '[]', 1);

    expect($process->getExitCode())->toBe(3)
        ->and($process->getErrorOutput())->toContain('rulesets unavailable');
})->skipOnWindows();

it('refuses a missing or malformed repository name', function (array $args): void {
    [$process, $calls] = runTagRuleset($args);

    expect($process->getExitCode())->toBe(2)
        ->and($process->getErrorOutput())->toContain('apply-tag-ruleset.py OWNER/REPO [--apply]')
        ->and($calls)->toBe('');
})->with([
    'no repository' => [[]],
    'no owner'      => [['widget']],
])->skipOnWindows();

it('applies both rulesets through one entry point that refuses a missing repository', function (): void {
    $process = new Process(['bash', dirname(__DIR__, 2) . '/scripts/apply-rulesets.sh']);
    $process->run();

    expect($process->getExitCode())->toBe(2)
        ->and($process->getErrorOutput())->toContain('apply-rulesets.sh OWNER/REPO [--pr NUMBER] [--keep-branches] [--apply]');
})->skipOnWindows();

/*
 * The wrapper's own work is the delete_branch_on_merge setting. A stub virtualenv stands in for
 * the two ruleset scripts (tested above and in their own right), so only the wrapper's calls run.
 */
function runRulesetsWrapper(array $args): array
{
    $bin = sys_get_temp_dir() . '/rulesets-wrapper-' . bin2hex(random_bytes(6));
    mkdir($bin . '/venv/bin', 0o755, true);
    $log = $bin . '/calls.log';

    file_put_contents($bin . '/gh', sprintf(<<<'SH'
        #!/usr/bin/env bash
        echo "gh $*" >> %s
        echo true
        SH, escapeshellarg($log)));
    file_put_contents($bin . '/venv/bin/python', sprintf(<<<'SH'
        #!/usr/bin/env bash
        [ "$1" = -c ] && exit 0
        echo "python $(basename "$1") ${*:2}" >> %s
        SH, escapeshellarg($log)));
    chmod($bin . '/gh', 0o755);
    chmod($bin . '/venv/bin/python', 0o755);

    $process = new Process(['bash', dirname(__DIR__, 2) . '/scripts/apply-rulesets.sh', ...$args], null, [
        'PATH'                => $bin . ':' . getenv('PATH'),
        'APPLY_RULESETS_VENV' => $bin . '/venv',
    ]);
    $process->run();

    return [$process, is_file($log) ? (string) file_get_contents($log) : ''];
}

it('turns on delete_branch_on_merge by default when applying', function (): void {
    [$process, $calls] = runRulesetsWrapper(['acme/widget', '--apply']);

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('acme/widget: delete_branch_on_merge=true')
        ->and($calls)->toContain('gh api -X PATCH repos/acme/widget -F delete_branch_on_merge=true')
        ->toContain('python apply-branch-ruleset.py acme/widget --apply')
        ->toContain('python apply-tag-ruleset.py acme/widget --apply');
})->skipOnWindows();

it('turns delete_branch_on_merge off with --keep-branches, and passes the flag to neither script', function (): void {
    [$process, $calls] = runRulesetsWrapper(['acme/widget', '--keep-branches', '--apply']);

    expect($process->getExitCode())->toBe(0)
        ->and($calls)->toContain('gh api -X PATCH repos/acme/widget -F delete_branch_on_merge=false')
        ->not->toContain('--keep-branches');
})->skipOnWindows();

it('only says what it would set on a dry run', function (): void {
    [$process, $calls] = runRulesetsWrapper(['acme/widget', '--pr', '12']);

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('would set delete_branch_on_merge=true (dry run)')
        ->and($calls)->not->toContain('gh ')
        ->toContain('python apply-branch-ruleset.py acme/widget --pr 12');
})->skipOnWindows();
