<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support;

/**
 * Reads a PHP config file without letting a missing one be fatal.
 *
 * Every caller here was written as check-then-require:
 *
 *     if (! File::isFile($path)) { return; }
 *     $config = require $path;
 *
 * which leaves a window where the file can disappear between the two lines. A
 * concurrent `vendor:publish --force`, an atomic config swap during a deploy,
 * or any other process rewriting the directory is enough - and `require` on a
 * missing file is a FATAL compile error, so the whole application fails to boot
 * over a file that was optional. try/catch does not help: a failed `require` is
 * not a catchable Throwable.
 *
 * `include` returns false instead of dying, which turns the race into the
 * outcome the callers already handle - "no override present".
 *
 * Found through a parallel test suite, where several processes share one
 * testbench skeleton and raced on exactly this path. The flakiness was the
 * symptom; the boot-time fatal is the defect.
 */
final class ConfigFile
{
    /**
     * The array a config file returns, or null when it is absent or malformed.
     *
     * @return array<string, mixed>|null
     */
    public static function read(string $path): ?array
    {
        // @ suppresses only the "failed to open stream" warning. A parse error
        // inside the file is still raised, which is what you want - a broken
        // config should be loud.
        $data = @include $path;

        return is_array($data) ? $data : null;
    }
}
