<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/*
 * bin/laranail-pint against a throwaway consuming package whose vendor/bin/pint is a stub, so
 * the wrapper's own behaviour is pinned without Pint. The stub has no shebang and is not
 * executable, which is exactly what a wrapper that runs it directly cannot cope with: it then
 * depends on whichever php the shebang finds on PATH (or, here, cannot run it at all), while
 * one that runs it under its own interpreter always reaches it.
 */
function laranailPintConsumer(): string
{
    $root = sys_get_temp_dir() . '/laranail-pint-' . bin2hex(random_bytes(5));
    $tools = $root . '/vendor/laranail/package-tools';

    @mkdir($tools . '/bin', 0777, true);
    @mkdir($root . '/vendor/bin', 0777, true);

    copy(dirname(__DIR__, 2) . '/bin/laranail-pint', $tools . '/bin/laranail-pint');
    copy(dirname(__DIR__, 2) . '/pint.json', $tools . '/pint.json');

    // A stub Pint that reports the interpreter running it and the arguments it was given.
    file_put_contents($root . '/vendor/bin/pint', "<?php\necho PHP_BINARY, \"\\n\", implode(' ', array_slice(\$argv, 1)), \"\\n\";\n");
    chmod($root . '/vendor/bin/pint', 0644);

    return $root;
}

it('runs Pint under the interpreter running the wrapper, not the php on PATH', function (): void {
    $root = laranailPintConsumer();

    $process = new Process([PHP_BINARY, $root . '/vendor/laranail/package-tools/bin/laranail-pint', '--test'], $root);
    $process->run();

    [$binary, $arguments] = array_pad(explode("\n", trim($process->getOutput()), 2), 2, '');

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput())
        ->and($binary)->toBe(PHP_BINARY)
        // The temp root may be reached through a symlink (/tmp vs /private/tmp), so match the tail.
        ->and($arguments)->toStartWith('--config ')
        ->toEndWith('/vendor/laranail/package-tools/pint.json --test');
});

it('still refuses to run without the shared config', function (): void {
    $root = laranailPintConsumer();
    unlink($root . '/vendor/laranail/package-tools/pint.json');

    $process = new Process([PHP_BINARY, $root . '/vendor/laranail/package-tools/bin/laranail-pint', '--test'], $root);
    $process->run();

    expect($process->getExitCode())->toBe(1)
        ->and($process->getErrorOutput())->toContain('The shared Pint config could not be found');
});
