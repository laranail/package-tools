<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/*
 * scripts/verify-trait-copies.py against a throwaway `packages/` tree, so the carrier rules are
 * pinned without the network. A same-named file that imports a carried trait -- console's, or
 * package-tools' as db-tools' deprecated forwarder does -- composes it and is not a copy. A file
 * that carries its own body is a copy, and a copy with no declared reason fails.
 */
function traitCopiesTree(string $consumerTrait): string
{
    $root = sys_get_temp_dir() . '/trait-copies-' . bin2hex(random_bytes(5));

    $write = static function (string $path, string $contents) use ($root): void {
        @mkdir(dirname($root . '/' . $path), 0777, true);
        file_put_contents($root . '/' . $path, $contents);
    };

    $write('console/.git/HEAD', "ref: refs/heads/main\n");
    $write('console/composer.json', json_encode(['name' => 'laranail/console', 'require' => ['php' => '^8.4.1']]));
    $write('console/src/Tools/Commands/Concerns/SupportsNamespacedNames.php', "<?php\ntrait SupportsNamespacedNames {}\n");
    $write('console/tests/NamespacedNamesConformanceTest.php', "<?php\n/** @trait-copy-canonical */\n");

    $write('consumer/.git/HEAD', "ref: refs/heads/main\n");
    $write('consumer/composer.json', json_encode(['name' => 'laranail/consumer', 'require' => ['php' => '^8.4.1', 'laranail/package-tools' => '^0.1']]));
    $write('consumer/src/Console/Concerns/SupportsNamespacedNames.php', $consumerTrait);
    $write('consumer/tests/NamespacedNamesConformanceTest.php', "<?php\n/** Conformance, with no declaration. */\n");

    return $root;
}

function runTraitCopies(string $consumerTrait): Process
{
    $process = new Process([
        'python3',
        dirname(__DIR__, 2) . '/scripts/verify-trait-copies.py',
        '--from-checkout',
        traitCopiesTree($consumerTrait),
    ]);
    $process->run();

    return $process;
}

it('treats a forwarder to package-tools\' trait as composing it, not carrying a copy', function (): void {
    $process = runTraitCopies(
        "<?php\nnamespace Acme\\Console\\Concerns;\n\n"
        . "use Simtabi\\Laranail\\Package\\Tools\\Commands\\Concerns\\SupportsNamespacedNames as Base;\n\n"
        . "trait SupportsNamespacedNames\n{\n    use Base;\n}\n",
    );

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('composes it    : consumer');
})->skipOnWindows();

it('treats a forwarder to console\'s trait as composing it', function (): void {
    $process = runTraitCopies(
        "<?php\nnamespace Acme\\Console\\Concerns;\n\n"
        . "use Simtabi\\Laranail\\Console\\Tools\\Commands\\Concerns\\SupportsNamespacedNames as Base;\n\n"
        . "trait SupportsNamespacedNames\n{\n    use Base;\n}\n",
    );

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('composes it    : consumer');
})->skipOnWindows();

it('fails an undeclared copy that imports no carried trait', function (): void {
    $process = runTraitCopies(
        "<?php\nnamespace Acme\\Console\\Concerns;\n\n"
        . "trait SupportsNamespacedNames\n{\n    public function setName(string \$name): static { return \$this; }\n}\n",
    );

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput())->toContain('declares neither @trait-copy-canonical nor @trait-copy-reason');
})->skipOnWindows();
