<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests;

use FilesystemIterator;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

/**
 * A config vendor segment that is unique to this test process.
 *
 * Every parallel worker shares ONE testbench skeleton, so they share
 * `vendor/orchestra/testbench-core/laravel/config/`. Laravel's LoadConfiguration
 * bootstrapper globs that directory recursively at boot, which means a file one
 * worker publishes is read by every other worker that happens to boot while it
 * exists - and deleted from under them when the owner tears down. The victim
 * dies on `Failed opening required '.../config/acme/widget.php'`, in a test that
 * has nothing to do with publishing.
 *
 * Only tests that WRITE into that directory need this. Tests that merely read a
 * config key are process-local and safe.
 */
final class ParallelSafe
{
    /**
     * A testbench skeleton this process has to itself.
     *
     * Naming the config file per worker is NOT enough. Testbench's
     * LoadConfiguration globs the whole config directory and requires every file
     * it finds, so worker 3 boots, picks up worker 9's file, and dies when
     * worker 9 deletes it. The directory has to be private, not just the name.
     *
     * The copy is made once per process and reused.
     */
    public static function isolatedSkeleton(): string
    {
        $token = $_SERVER['TEST_TOKEN'] ?? getenv('TEST_TOKEN') ?: (string) getmypid();
        $target = sys_get_temp_dir() . '/package-tools-skeleton-' . $token;

        if (! is_dir($target)) {
            $source = dirname(__DIR__) . '/vendor/orchestra/testbench-core/laravel';

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST,
            );

            mkdir($target, 0777, true);

            foreach ($iterator as $item) {
                $destination = $target . '/' . $iterator->getSubPathname();

                $item->isDir()
                    ? @mkdir($destination, 0777, true)
                    : @copy($item->getPathname(), $destination);
            }
        }

        return $target;
    }

    /**
     * `acme` when running serially, `acme3` under worker 3.
     */
    public static function vendor(string $base = 'acme'): string
    {
        $token = $_SERVER['TEST_TOKEN'] ?? getenv('TEST_TOKEN') ?: null;

        return $token === null ? $base : $base . $token;
    }
}
