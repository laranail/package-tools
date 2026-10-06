<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/*
 * scripts/verify-tag-currency.sh against a stub `gh` (tests/fixtures/gh-stub), so the policy is
 * pinned without the network. A published release must never be told to move, and a commit that
 * touches only .github/ (a weekly Dependabot bump) must not turn a released package's check red.
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

it('asks for a new release, never a moved tag, when shipped code is unreleased', function (): void {
    $process = runTagCurrency(['STUB_TAGS' => 'v0.1.0 v0.1.5', 'STUB_TAG_SHA' => 'aaa', 'STUB_HEAD' => 'bbb', 'STUB_FILES' => '.github/workflows/ci.yml src/Foo.php']);

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput())->toContain('cut v0.1.6')
        ->and($process->getOutput())->not->toContain('git tag -f');
})->skipOnWindows();

it('passes a moving-tag package whose later commits touch only .github/', function (): void {
    $process = runTagCurrency(['STUB_TAGS' => 'v0.1.0', 'STUB_TAG_SHA' => 'aaa', 'STUB_HEAD' => 'bbb', 'STUB_FILES' => '.github/workflows/ci.yml']);

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('touch only .github/');
})->skipOnWindows();

it('still asks a moving-tag package to move its tag when shipped code changed', function (): void {
    $process = runTagCurrency(['STUB_TAGS' => 'v0.1.0', 'STUB_TAG_SHA' => 'aaa', 'STUB_HEAD' => 'bbb', 'STUB_FILES' => 'src/Foo.php']);

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput())->toContain('Move it');
})->skipOnWindows();

it('passes a tag that is on main', function (): void {
    $process = runTagCurrency(['STUB_TAGS' => 'v0.1.0 v0.1.5', 'STUB_TAG_SHA' => 'bbb', 'STUB_HEAD' => 'bbb', 'STUB_FILES' => '']);

    expect($process->getExitCode())->toBe(0);
})->skipOnWindows();
