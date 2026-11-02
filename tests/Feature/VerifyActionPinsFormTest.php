<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/*
 * The form half of scripts/verify-action-pins.py: the shape of every `uses:` reference, which the
 * honesty and staleness halves cannot see because their pattern only matches a reference that is
 * already SHA-pinned with a comment. Driven against a `gh` that always fails (tests/fixtures/
 * gh-unavailable), so the network halves degrade to SKIPPED and the exit code is the form count
 * alone -- which also pins the promise that the form pass reports with no network.
 */
function runActionPinsForm(string $workflow, string $name = 'ci.yml'): Process
{
    $root = dirname(__DIR__, 2);
    $repo = sys_get_temp_dir() . '/pin-form-' . bin2hex(random_bytes(6));
    mkdir($repo . '/.github/workflows', 0o755, true);
    if ($workflow !== '') {
        file_put_contents($repo . '/.github/workflows/' . $name, $workflow);
    }

    $process = new Process(
        ['python3', $root . '/scripts/verify-action-pins.py', $repo],
        null,
        ['PATH' => $root . '/tests/fixtures/gh-unavailable' . PATH_SEPARATOR . getenv('PATH')],
    );
    $process->run();

    return $process;
}

function steps(string ...$uses): string
{
    return "jobs:\n  a:\n    steps:\n" . implode('', array_map(
        static fn (string $line): string => "      - uses: {$line}\n",
        $uses,
    ));
}

const CHECKOUT_SHA = '3d3c42e5aac5ba805825da76410c181273ba90b1';
const SBOM_SHA = '66cbf4bc1f1c0d2edc94016e65bc221b6bb0ad6c';
const DEPLOY_KEY_SHA = 'c56ac07ed456bdbefcc2220c8bf15f88868ee0bd';

it('fails a first-party action that is pinned to a SHA', function (): void {
    $process = runActionPinsForm(steps('actions/checkout@' . CHECKOUT_SHA . ' # v7.0.1'));

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput())->toContain('first-party, so float on a bare major tag');
})->skipOnWindows();

it('fails a third-party action left on a tag', function (): void {
    $process = runActionPinsForm(steps('shivammathur/setup-php@v2'));

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput())->toContain('third-party, so pin to a commit SHA');
})->skipOnWindows();

it('fails a SHA pin whose comment names a mutable major', function (): void {
    $process = runActionPinsForm(steps('anchore/sbom-action@' . SBOM_SHA . ' # v0'));

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput())->toContain('name the release, not a mutable major');
})->skipOnWindows();

it('fails a SHA pin with no comment at all', function (): void {
    $process = runActionPinsForm(steps('anchore/sbom-action@' . SBOM_SHA));

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput())->toContain('no comment, so nothing says which release this is');
})->skipOnWindows();

it('exempts the missing comment only for an upstream that publishes no release', function (): void {
    $process = runActionPinsForm(steps(
        'laranail/.github/.github/actions/deploy-key@' . DEPLOY_KEY_SHA,
    ));

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->not->toContain('FORM');
})->skipOnWindows();

it('passes the rule: first-party floating, third-party on a named release', function (): void {
    $process = runActionPinsForm(steps(
        'actions/checkout@v7',
        'docker/login-action@v4',
        'anchore/sbom-action@' . SBOM_SHA . ' # v0.24.3',
    ));

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->not->toContain('FORM');
})->skipOnWindows();

it('skips a local action and a reusable-workflow call, which are not actions', function (): void {
    $process = runActionPinsForm(steps(
        './.github/actions/local',
        'laranail/.github/.github/workflows/hygiene.yml@' . DEPLOY_KEY_SHA,
        'actions/checkout@v7',
    ));

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->not->toContain('FORM');
})->skipOnWindows();

it('reads a .yaml workflow, which globbing *.yml silently skipped', function (): void {
    $process = runActionPinsForm(steps('shivammathur/setup-php@v2'), 'ci.yaml');

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput())->toContain('(ci.yaml)');
})->skipOnWindows();

it('refuses to report a clean repository when the workflows directory is empty', function (): void {
    $process = runActionPinsForm('');

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput())->toContain('Refusing to report a clean repository');
})->skipOnWindows();

it('still reports the form half when gh cannot answer', function (): void {
    $process = runActionPinsForm(steps(
        'actions/checkout@' . CHECKOUT_SHA . ' # v7.0.1',
        'anchore/sbom-action@' . SBOM_SHA . ' # v0.24.3',
    ));

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput())->toContain('FORM')
        ->and($process->getOutput())->toContain('SKIPPED');
})->skipOnWindows();
