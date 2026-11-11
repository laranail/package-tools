<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/*
 * scripts/prune-merged-branches.sh: the local half of "delete a branch after it merges".
 *
 * Each test builds a real repository against a bare remote in a temporary directory. The remote
 * branches are deleted in the bare repository itself, so it is the script's own `fetch --prune`
 * that discovers them gone.
 */
function pruneGit(string $dir, string ...$args): string
{
    $process = new Process(['git', '-C', $dir, ...$args], null, [
        'GIT_CONFIG_GLOBAL'   => '/dev/null',
        'GIT_CONFIG_NOSYSTEM' => '1',
        'GIT_AUTHOR_NAME'     => 'Test', 'GIT_AUTHOR_EMAIL' => 'test@example.invalid',
        'GIT_COMMITTER_NAME'  => 'Test', 'GIT_COMMITTER_EMAIL' => 'test@example.invalid',
    ]);
    $process->mustRun();

    return trim($process->getOutput());
}

function pruneCommit(string $work, string $file): void
{
    file_put_contents($work . '/' . $file, $file);
    pruneGit($work, 'add', $file);
    pruneGit($work, 'commit', '--quiet', '-m', 'Add ' . $file);
}

/**
 * main, plus four branches: merged and gone, unmerged and gone, never pushed, and the current
 * branch, which is merged and gone too, so only the current-branch rule keeps it.
 */
function pruneRepository(): string
{
    $root = sys_get_temp_dir() . '/prune-' . bin2hex(random_bytes(6));
    mkdir($root, 0o755, true);
    $bare = $root . '/remote.git';
    $work = $root . '/work';

    (new Process(['git', 'init', '--quiet', '--bare', '-b', 'main', $bare]))->mustRun();
    (new Process(['git', 'init', '--quiet', '-b', 'main', $work]))->mustRun();
    pruneGit($work, 'remote', 'add', 'origin', $bare);
    pruneCommit($work, 'README');
    pruneGit($work, 'push', '--quiet', '-u', 'origin', 'main');

    foreach (['feat/merged', 'feat/unmerged', 'feat/current'] as $branch) {
        pruneGit($work, 'switch', '--quiet', '-c', $branch, 'main');
        pruneCommit($work, str_replace('/', '-', $branch));
        pruneGit($work, 'push', '--quiet', '-u', 'origin', $branch);
    }
    pruneGit($work, 'switch', '--quiet', '-c', 'feat/local', 'main');
    pruneCommit($work, 'local');

    pruneGit($work, 'switch', '--quiet', 'main');
    pruneGit($work, 'merge', '--quiet', '--no-ff', '-m', 'Merge merged', 'feat/merged');
    pruneGit($work, 'merge', '--quiet', '--no-ff', '-m', 'Merge current', 'feat/current');
    pruneGit($work, 'push', '--quiet', 'origin', 'main');

    foreach (['feat/merged', 'feat/unmerged', 'feat/current'] as $branch) {
        pruneGit($bare, 'branch', '-D', $branch);
    }
    pruneGit($work, 'switch', '--quiet', 'feat/current');

    return $work;
}

function runPrune(string $work, bool $apply): Process
{
    $process = new Process(['bash', dirname(__DIR__, 2) . '/scripts/prune-merged-branches.sh', $work, ...($apply ? ['--apply'] : [])], null, [
        'GIT_CONFIG_GLOBAL'   => '/dev/null',
        'GIT_CONFIG_NOSYSTEM' => '1',
    ]);
    $process->run();

    return $process;
}

it('deletes only the branch that is merged and gone, and its config section', function (): void {
    $work = pruneRepository();

    $process = runPrune($work, true);

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())
        ->toContain('delete  feat/merged  (upstream gone, merged into origin/main)')
        ->toContain('keep    feat/unmerged  (upstream gone, but not merged into origin/main)')
        ->toContain('keep    feat/local  (never pushed (no upstream))')
        ->toContain('keep    feat/current  (current branch)')
        ->toContain('keep    main  (default branch)')
        ->toContain('Deleted 1, kept 4.')
        ->and(explode("\n", pruneGit($work, 'for-each-ref', '--format=%(refname:short)', 'refs/heads')))
        ->toBe(['feat/current', 'feat/local', 'feat/unmerged', 'main'])
        ->and(pruneGit($work, 'config', '--get-regexp', '^branch\.'))->not->toContain('feat/merged');
})->skipOnWindows();

it('deletes nothing on a dry run', function (): void {
    $work = pruneRepository();

    $process = runPrune($work, false);

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('delete  feat/merged')
        ->toContain('Dry run: would delete 1, keep 4.')
        ->and(pruneGit($work, 'for-each-ref', '--format=%(refname:short)', 'refs/heads'))->toContain('feat/merged');
})->skipOnWindows();

it('refuses a directory that is not a git checkout', function (): void {
    $dir = sys_get_temp_dir() . '/prune-empty-' . bin2hex(random_bytes(6));
    mkdir($dir, 0o755, true);

    $process = runPrune($dir, false);

    expect($process->getExitCode())->toBe(2)
        ->and($process->getErrorOutput())->toContain('not a git checkout');
})->skipOnWindows();
