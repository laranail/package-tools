<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/*
 * scripts/verify-tag-currency.sh against a stub `gh` (tests/fixtures/gh-stub), so the policy is
 * pinned without the network. Tags are immutable: a tag behind main is a release that is due, which
 * warns and exits 0, and nothing the script prints may suggest moving a tag. A tag off main's history,
 * or a live branch-alias line with no tag, is a real defect and fails.
 */
function runTagCurrency(array $env): Process
{
    $root = dirname(__DIR__, 2);
    $process = new Process(
        ['bash', $root . '/scripts/verify-tag-currency.sh', 'laranail/example'],
        null,
        $env + ['PATH' => $root . '/tests/fixtures/gh-stub' . PATH_SEPARATOR . getenv('PATH'), 'STUB_ALIAS' => '0.1.x-dev'],
    );
    $process->run();

    return $process;
}

it('passes a release whose later commits touch only .github/', function (): void {
    $process = runTagCurrency(['STUB_TAGS' => 'v0.1.0 v0.1.5', 'STUB_TAG_SHA' => 'aaa', 'STUB_HEAD' => 'bbb', 'STUB_FILES' => '.github/workflows/ci.yml']);

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('touch only .github/');
})->skipOnWindows();

it('warns that a release is due, and exits 0, when shipped code is unreleased', function (): void {
    $process = runTagCurrency(['STUB_TAGS' => 'v0.1.0 v0.1.5', 'STUB_TAG_SHA' => 'aaa', 'STUB_HEAD' => 'bbb', 'STUB_FILES' => '.github/workflows/ci.yml src/Foo.php']);

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('::warning title=Release due::release due: cut v0.1.6');
})->skipOnWindows();

it('passes a single-tag package whose later commits touch only .github/', function (): void {
    $process = runTagCurrency(['STUB_TAGS' => 'v0.1.0', 'STUB_TAG_SHA' => 'aaa', 'STUB_HEAD' => 'bbb', 'STUB_FILES' => '.github/workflows/ci.yml']);

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('touch only .github/');
})->skipOnWindows();

it('asks a single-tag package for the next patch, not a moved tag, when shipped code changed', function (): void {
    $process = runTagCurrency(['STUB_TAGS' => 'v0.1.0', 'STUB_TAG_SHA' => 'aaa', 'STUB_HEAD' => 'bbb', 'STUB_FILES' => 'src/Foo.php']);

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('release due: cut v0.1.1');
})->skipOnWindows();

it('writes a due release to the job summary', function (): void {
    $summary = tempnam(sys_get_temp_dir(), 'summary');
    $process = runTagCurrency(['STUB_TAGS' => 'v0.1.0', 'STUB_TAG_SHA' => 'aaa', 'STUB_HEAD' => 'bbb', 'STUB_FILES' => 'src/Foo.php', 'GITHUB_STEP_SUMMARY' => $summary]);

    expect($process->getExitCode())->toBe(0)
        ->and((string) file_get_contents($summary))->toContain('release due: cut v0.1.1');

    unlink($summary);
})->skipOnWindows();

it('fails a tag that is not an ancestor of main', function (): void {
    $process = runTagCurrency(['STUB_TAGS' => 'v0.1.0', 'STUB_TAG_SHA' => 'aaa', 'STUB_HEAD' => 'bbb', 'STUB_FILES' => 'src/Foo.php', 'STUB_STATUS' => 'diverged']);

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput())->toContain('is not an ancestor of main');
})->skipOnWindows();

it('fails a live branch-alias line that has no tag', function (): void {
    $process = runTagCurrency(['STUB_TAGS' => 'v0.1.0', 'STUB_TAG_SHA' => 'bbb', 'STUB_HEAD' => 'bbb', 'STUB_FILES' => '', 'STUB_ALIAS' => '0.2.x-dev']);

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput())->toContain('no v0.2.* tag exists');
})->skipOnWindows();

it('never suggests moving or force-pushing a tag, in any outcome', function (array $env): void {
    $process = runTagCurrency($env);
    $output = $process->getOutput() . $process->getErrorOutput();

    expect($output)->not->toContain('tag -f')
        ->and($output)->not->toContain('--force');
})->with([
    'behind, several tags' => [['STUB_TAGS' => 'v0.1.0 v0.1.5', 'STUB_TAG_SHA' => 'aaa', 'STUB_HEAD' => 'bbb', 'STUB_FILES' => 'src/Foo.php']],
    'behind, one tag'      => [['STUB_TAGS' => 'v0.1.0', 'STUB_TAG_SHA' => 'aaa', 'STUB_HEAD' => 'bbb', 'STUB_FILES' => 'src/Foo.php']],
    'not an ancestor'      => [['STUB_TAGS' => 'v0.1.0', 'STUB_TAG_SHA' => 'aaa', 'STUB_HEAD' => 'bbb', 'STUB_FILES' => 'src/Foo.php', 'STUB_STATUS' => 'diverged']],
    'on main'              => [['STUB_TAGS' => 'v0.1.0', 'STUB_TAG_SHA' => 'bbb', 'STUB_HEAD' => 'bbb', 'STUB_FILES' => '']],
])->skipOnWindows();

it('passes a tag that is on main', function (): void {
    $process = runTagCurrency(['STUB_TAGS' => 'v0.1.0 v0.1.5', 'STUB_TAG_SHA' => 'bbb', 'STUB_HEAD' => 'bbb', 'STUB_FILES' => '']);

    expect($process->getExitCode())->toBe(0);
})->skipOnWindows();
