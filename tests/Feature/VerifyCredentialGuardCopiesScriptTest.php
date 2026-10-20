<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;
use Simtabi\Laranail\Package\Tools\Testing\CredentialLiterals;

/*
 * scripts/verify-credential-guard-copies.py against a throwaway `packages/` tree, so its rules
 * are pinned without the network: a shared guard passes, a declared copy whose reason holds and
 * whose mirrored version is current passes, and an undeclared copy, a stale copy or a reason that
 * no longer holds each fail.
 */
function guardCopiesTree(array $repos): string
{
    $root = sys_get_temp_dir() . '/guard-copies-' . bin2hex(random_bytes(5));

    foreach ($repos as $name => [$composer, $guard]) {
        @mkdir("{$root}/{$name}/.git", 0777, true);
        @mkdir("{$root}/{$name}/tests/Architecture", 0777, true);
        file_put_contents("{$root}/{$name}/composer.json", json_encode($composer));
        file_put_contents("{$root}/{$name}/tests/Architecture/NoLiteralCredentialsTest.php", $guard);
    }

    return $root;
}

function runGuardCopies(array $repos): Process
{
    $process = new Process(['python3', dirname(__DIR__, 2) . '/scripts/verify-credential-guard-copies.py', '--from-checkout', guardCopiesTree($repos)]);
    $process->run();

    return $process;
}

function copyDeclaring(string $reason, string $mirrors): string
{
    return "<?php\n/**\n * @credential-guard-copy reason={$reason} mirrors={$mirrors}\n */\n";
}

it('passes the shared guard and a current, justified copy', function (): void {
    $process = runGuardCopies([
        'shared'   => [['name' => 'laranail/shared', 'require' => ['php' => '^8.4.1']], "<?php\nuse Simtabi\\Laranail\\Package\\Tools\\Testing\\AssertsNoLiteralCredentials;\n"],
        'old-php'  => [['name' => 'laranail/old-php', 'require' => ['php' => '^8.3']], copyDeclaring('php-floor-below-package-tools', CredentialLiterals::RULES_VERSION)],
        'outsider' => [['name' => 'simtabi/outsider', 'require' => ['php' => '^8.5']], copyDeclaring('outside-laranail', CredentialLiterals::RULES_VERSION)],
    ]);

    expect($process->getExitCode())->toBe(0, $process->getOutput())
        ->and($process->getOutput())->toContain('Inspected 3 repositories: 1 shared guard(s), 2 local cop(ies).');
});

it('fails an undeclared copy, a stale copy and a reason that no longer holds', function (): void {
    $process = runGuardCopies([
        'undeclared' => [['name' => 'laranail/undeclared', 'require' => ['php' => '^8.3']], "<?php\n// a hand-rolled scanner\n"],
        'stale'      => [['name' => 'laranail/stale', 'require' => ['php' => '^8.3']], copyDeclaring('php-floor-below-package-tools', '0.1.0')],
        'expired'    => [['name' => 'laranail/expired', 'require' => ['php' => '^8.4.1 || ^8.5']], copyDeclaring('php-floor-below-package-tools', CredentialLiterals::RULES_VERSION)],
        'insider'    => [['name' => 'laranail/insider', 'require' => ['php' => '^8.5']], copyDeclaring('outside-laranail', CredentialLiterals::RULES_VERSION)],
    ]);

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput())
        ->toContain('undeclared/tests/Architecture/NoLiteralCredentialsTest.php: a local copy with no')
        ->toContain('stale/tests/Architecture/NoLiteralCredentialsTest.php: mirrors 0.1.0')
        ->toContain('expired/tests/Architecture/NoLiteralCredentialsTest.php: reason=php-floor-below-package-tools: floor 8.4.1 is not below')
        ->toContain('insider/tests/Architecture/NoLiteralCredentialsTest.php: reason=outside-laranail: the package is laranail/insider');
});

it('refuses to pass when it inspected nothing', function (): void {
    $root = sys_get_temp_dir() . '/guard-copies-empty-' . bin2hex(random_bytes(5));
    mkdir($root);

    $process = new Process(['python3', dirname(__DIR__, 2) . '/scripts/verify-credential-guard-copies.py', '--from-checkout', $root]);
    $process->run();

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput())->toContain('Nothing was inspected');
});
