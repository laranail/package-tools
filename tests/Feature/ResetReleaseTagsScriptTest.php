<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/*
 * scripts/reset-release-tags.py: the one sanctioned way to move or replace release tags.
 *
 * A fake `gh` on PATH answers reads from a fixture keyed by the exact arguments, records every
 * call, and fails any call matching FAKE_GH_FAIL, so the ruleset's disable/re-enable bracket is
 * checked on the failure path too. Packagist is probed through PACKAGIST_BASE, pointed at a local
 * directory, so no test reaches the network.
 */
const RESET_SHA = '1111111111111111111111111111111111111111';
const RESET_OLD = '2222222222222222222222222222222222222222';

function resetFixture(string $compareStatus = 'behind'): array
{
    return [
        'api repos/acme/widget'                                   => ['default_branch' => 'main'],
        'api repos/acme/widget/compare/main...' . RESET_SHA       => ['status' => $compareStatus, 'merge_base_commit' => ['sha' => RESET_SHA]],
        'api --paginate repos/acme/widget/git/matching-refs/tags' => [
            ['ref' => 'refs/tags/v0.1.0', 'object' => ['sha' => RESET_OLD, 'type' => 'commit']],
            ['ref' => 'refs/tags/v0.2.0', 'object' => ['sha' => '3333333333333333333333333333333333333333', 'type' => 'tag']],
            ['ref' => 'refs/tags/src/v0.1.0', 'object' => ['sha' => RESET_OLD, 'type' => 'commit']],
            ['ref' => 'refs/tags/latest', 'object' => ['sha' => RESET_OLD, 'type' => 'commit']],
            ['ref' => 'refs/tags/v1', 'object' => ['sha' => RESET_OLD, 'type' => 'commit']],
        ],
        'api repos/acme/widget/git/tags/3333333333333333333333333333333333333333' => ['object' => ['sha' => '4444444444444444444444444444444444444444']],
        'api --paginate repos/acme/widget/releases'                               => [
            ['id' => 11, 'tag_name' => 'v0.1.0', 'draft' => false],
            ['id' => 12, 'tag_name' => 'v0.2.0', 'draft' => true],
            ['id' => 13, 'tag_name' => 'nightly', 'draft' => false],
        ],
        'api repos/acme/widget/rulesets?includes_parents=false' => [['id' => 7, 'name' => 'Protect release tags', 'target' => 'tag']],
        'api repos/acme/widget/rulesets/7'                      => ['id' => 7, 'enforcement' => 'active'],
    ];
}

function runResetTags(array $args, ?array $fixture = null, string $failOn = ''): array
{
    $root = dirname(__DIR__, 2);
    $bin = sys_get_temp_dir() . '/reset-tags-' . bin2hex(random_bytes(6));
    mkdir($bin . '/packagist/p2/acme', 0o755, true);
    file_put_contents($bin . '/packagist/p2/acme/widget.json', '{"packages": {}}');
    file_put_contents($bin . '/fixture.json', json_encode($fixture ?? resetFixture()));
    file_put_contents($bin . '/gh', <<<'PY'
        #!/usr/bin/env python3
        import json, os, sys
        here = os.path.dirname(os.path.abspath(__file__))
        args = sys.argv[1:]
        stdin = sys.stdin.read() if "--input" in args else ""
        key = " ".join(a for a in args if a not in ("--input", "-"))
        with open(os.path.join(here, "calls.log"), "a") as log:
            log.write(key + (" " + stdin if stdin else "") + "\n")
        fail = os.environ.get("FAKE_GH_FAIL", "")
        if fail and fail in key:
            print("HTTP 422: fake failure", file=sys.stderr)
            sys.exit(1)
        fixture = json.load(open(os.path.join(here, "fixture.json")))
        print(json.dumps(fixture.get(key, {})))
        PY);
    chmod($bin . '/gh', 0o755);

    $process = new Process(['python3', $root . '/scripts/reset-release-tags.py', ...$args], null, [
        'PATH'           => $bin . ':' . getenv('PATH'),
        'PACKAGIST_BASE' => 'file://' . $bin . '/packagist',
        'FAKE_GH_FAIL'   => $failOn,
    ]);
    $process->run();

    return [$process, is_file($bin . '/calls.log') ? (string) file_get_contents($bin . '/calls.log') : ''];
}

function resetWriteCalls(string $calls): array
{
    return array_values(array_filter(explode("\n", $calls), fn (string $line): bool => (bool) preg_match('/^api -X /', $line)));
}

it('lists what a fresh reset would do on a dry run and writes nothing', function (): void {
    [$process, $calls] = runResetTags(['acme/widget', '--fresh', 'v0.1.0', '--to', RESET_SHA]);

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())
        ->toContain('delete   v0.1.0  222222222222')
        ->toContain('delete   v0.2.0  444444444444')
        ->toContain('delete   src/v0.1.0  222222222222')
        ->toContain('delete   release v0.2.0 (id 12, draft)')
        ->toContain('create   v0.1.0  111111111111')
        ->toContain('keep     latest')
        ->toContain('keep     v1 ')
        ->toContain('p2/acme/widget.json -> 200 (listed)')
        ->toContain('composer clear-cache')
        ->toContain('proxy.golang.org')
        ->toContain('Dry run. Nothing changed.')
        ->not->toContain('release nightly')
        ->and(resetWriteCalls($calls))->toBe([]);
})->skipOnWindows();

it('refuses --apply without the exact --confirm, before calling GitHub', function (array $extra): void {
    [$process, $calls] = runResetTags(['acme/widget', '--fresh', 'v0.1.0', '--to', RESET_SHA, '--apply', ...$extra]);

    expect($process->getExitCode())->toBe(2)
        ->and($process->getErrorOutput())->toContain('--confirm acme/widget, typed exactly')
        ->and($calls)->toBe('');
})->with([
    'no confirm'    => [[]],
    'wrong confirm' => [['--confirm', 'acme/other']],
    'wrong case'    => [['--confirm', 'Acme/Widget']],
])->skipOnWindows();

it('refuses a SHA that is not on the default branch', function (string $status): void {
    [$process, $calls] = runResetTags(
        ['acme/widget', '--move', 'v0.1.0', '--to', RESET_SHA, '--apply', '--confirm', 'acme/widget'],
        resetFixture($status),
    );

    expect($process->getExitCode())->toBe(1)
        ->and($process->getErrorOutput())->toContain("is not on acme/widget's default branch main")
        ->and(resetWriteCalls($calls))->toBe([]);
})->with(['ahead', 'diverged'])->skipOnWindows();

it('moves one tag and its src alias between disabling and re-enabling the ruleset', function (): void {
    [$process, $calls] = runResetTags(['acme/widget', '--move', 'v0.1.0', '--to', RESET_SHA, '--apply', '--confirm', 'acme/widget']);

    expect($process->getExitCode())->toBe(0)
        ->and(resetWriteCalls($calls))->toBe([
            'api -X PUT repos/acme/widget/rulesets/7 {"enforcement": "disabled"}',
            'api -X PATCH repos/acme/widget/git/refs/tags/v0.1.0 {"sha": "' . RESET_SHA . '", "force": true}',
            'api -X PATCH repos/acme/widget/git/refs/tags/src/v0.1.0 {"sha": "' . RESET_SHA . '", "force": true}',
            'api -X PUT repos/acme/widget/rulesets/7 {"enforcement": "active"}',
        ])
        ->and($process->getOutput())->toContain('Ruleset 7 (Protect release tags): active');
})->skipOnWindows();

it('replaces every version tag and release with one fresh tag, leaving other tags alone', function (): void {
    [$process, $calls] = runResetTags(['acme/widget', '--fresh', 'v0.1.0', '--to', RESET_SHA, '--apply', '--confirm', 'acme/widget']);

    $writes = resetWriteCalls($calls);

    expect($process->getExitCode())->toBe(0)
        ->and($writes)->toBe([
            'api -X PUT repos/acme/widget/rulesets/7 {"enforcement": "disabled"}',
            'api -X DELETE repos/acme/widget/releases/11',
            'api -X DELETE repos/acme/widget/releases/12',
            'api -X DELETE repos/acme/widget/git/refs/tags/src/v0.1.0',
            'api -X DELETE repos/acme/widget/git/refs/tags/v0.1.0',
            'api -X DELETE repos/acme/widget/git/refs/tags/v0.2.0',
            'api -X POST repos/acme/widget/git/refs {"ref": "refs/tags/v0.1.0", "sha": "' . RESET_SHA . '"}',
            'api -X PUT repos/acme/widget/rulesets/7 {"enforcement": "active"}',
        ])
        ->and(implode("\n", $writes))->not->toContain('tags/latest')->not->toContain('tags/v1 ')->not->toContain('releases/13');
})->skipOnWindows();

it('re-enables the ruleset even when the work fails part-way', function (): void {
    [$process, $calls] = runResetTags(
        ['acme/widget', '--fresh', 'v0.1.0', '--to', RESET_SHA, '--apply', '--confirm', 'acme/widget'],
        null,
        'git/refs/tags/v0.1.0',
    );

    $writes = resetWriteCalls($calls);

    expect($process->getExitCode())->toBe(1)
        ->and($process->getErrorOutput())->toContain('fake failure')
        ->and(end($writes))->toBe('api -X PUT repos/acme/widget/rulesets/7 {"enforcement": "active"}')
        ->and(implode("\n", $writes))->not->toContain('-X POST')
        ->and($process->getOutput())->toContain('Ruleset 7 (Protect release tags): active');
})->skipOnWindows();

it('refuses a malformed invocation', function (array $args): void {
    [$process, $calls] = runResetTags($args);

    expect($process->getExitCode())->toBe(2)
        ->and($calls)->toBe('');
})->with([
    'no repository'  => [['--fresh', 'v0.1.0', '--to', RESET_SHA]],
    'both modes'     => [['acme/widget', '--move', 'v0.1.0', '--fresh', 'v0.1.0', '--to', RESET_SHA]],
    'no target'      => [['acme/widget', '--move', 'v0.1.0']],
    'not a version'  => [['acme/widget', '--fresh', 'latest', '--to', RESET_SHA]],
    'not a sha'      => [['acme/widget', '--fresh', 'v0.1.0', '--to', 'main']],
    'unknown option' => [['acme/widget', '--fresh', 'v0.1.0', '--to', RESET_SHA, '--force']],
])->skipOnWindows();

it('lifts every active tag ruleset whatever its name, and leaves branch and disabled rulesets alone', function (): void {
    $fixture = resetFixture();
    $fixture['api repos/acme/widget/rulesets?includes_parents=false'] = [
        ['id' => 7, 'name' => 'release tags are final', 'target' => 'tag', 'enforcement' => 'active'],
        ['id' => 8, 'name' => 'Protect release tags', 'target' => 'tag', 'enforcement' => 'active'],
        ['id' => 9, 'name' => 'old tag rule', 'target' => 'tag', 'enforcement' => 'disabled'],
        ['id' => 10, 'name' => 'default branch', 'target' => 'branch', 'enforcement' => 'active'],
    ];
    $fixture['api repos/acme/widget/rulesets/8'] = ['id' => 8, 'enforcement' => 'active'];
    [$process, $calls] = runResetTags(['acme/widget', '--move', 'v0.1.0', '--to', RESET_SHA, '--apply', '--confirm', 'acme/widget'], $fixture);

    $writes = resetWriteCalls($calls);
    expect($process->getExitCode())->toBe(0)
        ->and($writes[0])->toBe('api -X PUT repos/acme/widget/rulesets/7 {"enforcement": "disabled"}')
        ->and($writes[1])->toBe('api -X PUT repos/acme/widget/rulesets/8 {"enforcement": "disabled"}')
        ->and(array_slice($writes, -2))->toBe([
            'api -X PUT repos/acme/widget/rulesets/7 {"enforcement": "active"}',
            'api -X PUT repos/acme/widget/rulesets/8 {"enforcement": "active"}',
        ])
        ->and($calls)->not->toContain('rulesets/9 ')
        ->not->toContain('rulesets/10 ')
        ->and($process->getOutput())->toContain('Ruleset 7 (release tags are final): active');
})->skipOnWindows();
