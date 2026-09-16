<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Testing;

use ReflectionClass;
use PHPUnit\Framework\Assert;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

/**
 * Test helpers for the two failure modes that a mocked test structurally cannot reach: a driver name
 * that resolves to nothing, and a config key that is read where nothing is registered.
 *
 * Both are silent. `Illuminate\Support\Manager` resolves a driver by **interpolating its name into a
 * method name** — `driver('telnyx')` becomes `createTelnyxDriver()` — so a name with no method is an
 * `InvalidArgumentException` at the first real use, in production, long after a green suite. And
 * `config('foo.key')` on an unregistered key returns null (or the call site's inline default), so a
 * package reading its own config at the wrong key keeps running on defaults forever.
 *
 * Neither is catchable by a test that mocks the manager or sets the config itself: a mock answers
 * whatever the test asked it to, and a harness that writes the bare key creates the very tree the
 * package is reading. `laranail/authkit-social` shipped two providers that could not authenticate at
 * all behind a fully green suite; `laranail/env-tools` shipped a config where every value was inert.
 *
 * Mix into any Testbench/Orchestra test case:
 *
 * ```php
 * use AssertsDriverContract;
 *
 * $this->assertEveryDriverIsBuildable(SmsManager::class, array_keys($shipped['drivers']));
 * $this->assertReadsConfigAtRegisteredKey(__DIR__ . '/../../src', 'sms');
 * ```
 *
 * Drive the driver list off the shipped config file or an enum's `cases()`, never a hand-written
 * array — a list written by hand tests the list, not the package, and can be wrong on the day it is
 * written.
 */
trait AssertsDriverContract
{
    /**
     * The method name `Illuminate\Support\Manager` will look for, for a given driver name.
     *
     * Manager studlies the name, so `lemon-squeezy` resolves `createLemonSqueezyDriver()`.
     */
    protected static function managerCreateMethod(string $driver): string
    {
        return 'create' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $driver))) . 'Driver';
    }

    /**
     * Assert that every name in $driverNames resolves to a `create*Driver()` method on $managerClass.
     *
     * Performs no I/O and needs no credentials: the question is whether a driver is *constructible*,
     * not whether the remote service answers.
     *
     * @param class-string $managerClass A class extending Illuminate\Support\Manager.
     * @param iterable<array-key, string> $driverNames Every name the configuration can produce.
     */
    protected function assertEveryDriverIsBuildable(string $managerClass, iterable $driverNames): void
    {
        $manager = new ReflectionClass($managerClass);
        $names = [];
        $offenders = [];

        foreach ($driverNames as $name) {
            $names[] = $name;
            $method = self::managerCreateMethod($name);

            if (! $manager->hasMethod($method)) {
                $offenders[$name] ??= "{$name} => {$method}()";
            }
        }

        // A discovery-driven assertion that discovers nothing passes trivially, which is worse than
        // no assertion: it reads as coverage.
        Assert::assertNotEmpty(
            $names,
            "No driver names were supplied for {$managerClass}, so this assertion proves nothing. "
            . 'Check that the fixture is reading the shipped config rather than an empty array.',
        );

        Assert::assertSame([], array_values($offenders), sprintf(
            "%s cannot build every driver its configuration can name:\n  %s",
            $managerClass,
            implode("\n  ", $offenders),
        ));
    }

    /**
     * Assert that no file under $sourceDir reads configuration at the bare $bareKey.
     *
     * Asserted against the source rather than the container, because this defect lives in lines that
     * a test never executes — and because a test harness that sets the bare key hides it completely.
     * Use it *alongside* a behavioural assertion, not instead of one.
     *
     * @param string $sourceDir Absolute path to the package's src/ directory.
     * @param string $bareKey The unnamespaced config key, e.g. 'sms'.
     * @param list<string> $exempt First key segments that are a DIFFERENT registry and keep their
     *                             own bare names — container tags, gate abilities, queue names.
     */
    protected function assertReadsConfigAtRegisteredKey(string $sourceDir, string $bareKey, array $exempt = []): void
    {
        $offenders = [];
        $quoted = preg_quote($bareKey, '/');

        // Matches `config('key…')` and `->get('key…')`, for a bare key, a dotted one of any depth,
        // and an interpolated "key.{$suffix}". Anchoring the closing quote after the first segment
        // is the obvious mistake and it silently misses every multi-segment key.
        $pattern = '/(?:config\(|->get\()\s*[\'"]' . $quoted . '(?:\.([a-z_]+))?(?:\.[a-z_.]+)?[\'"{]/';

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            foreach (file($file->getPathname()) ?: [] as $number => $line) {
                if (preg_match($pattern, $line, $matches) !== 1) {
                    continue;
                }

                if (in_array($matches[1] ?? '', $exempt, true)) {
                    continue;
                }

                $offenders[] = basename($file->getPathname()) . ':' . ($number + 1) . ' — ' . trim($line);
            }
        }

        Assert::assertSame([], $offenders, sprintf(
            'These read config at the bare key [%s], which resolves to nothing in a real '
            . "application — each silently returns its own inline default:\n  %s",
            $bareKey,
            implode("\n  ", $offenders),
        ));
    }
}
