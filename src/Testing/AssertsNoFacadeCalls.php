<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Testing;

use PHPUnit\Framework\Assert;

/**
 * Keeps a package's own classes off facades and service-locating helpers, so every dependency
 * is in a constructor where a test and a reader can see it.
 *
 * A package MAY ship a facade for its consumers. Its own classes take the contract instead
 * (`Illuminate\Contracts\Cache\Repository`, not `Cache::`; an injected `Config\Repository`, not
 * `config()`). Service providers are the composition root and are exempt, as is the facade
 * class itself. What counts is {@see FacadeCalls::in()}.
 *
 * ```php
 * uses(AssertsNoFacadeCalls::class);
 *
 * it('keeps the package classes off facades', function (): void {
 *     $this->assertNoFacadeCallsIn(dirname(__DIR__) . '/src', minFiles: 10);
 * });
 * ```
 *
 * A call that cannot be injected stays, marked on its line or the line above with
 * {@see FACADE_CALL_EXEMPTION} and a reason.
 */
trait AssertsNoFacadeCalls
{
    public const string FACADE_CALL_EXEMPTION = FacadeCalls::EXEMPTION;

    /**
     * @param string|list<string> $dirs Source directories to scan, usually `src/`.
     * @param int $minFiles The fewest PHP files the scan may inspect, so a path
     *                      typo fails rather than reporting a clean package.
     * @param list<string> $exemptFiles Basenames to skip. Prefer the line marker.
     */
    protected function assertNoFacadeCallsIn(string|array $dirs, int $minFiles = 1, array $exemptFiles = []): void
    {
        $dirs = (array) $dirs;
        foreach ($dirs as $dir) {
            Assert::assertDirectoryExists($dir);
        }

        [$scanned, $offenders] = FacadeCalls::scan($dirs, $exemptFiles);

        Assert::assertGreaterThanOrEqual($minFiles, $scanned, sprintf(
            'Only %d PHP file(s) were scanned, fewer than the floor of %d, so this assertion proved '
            . 'little. Check the directories passed to it.',
            $scanned,
            $minFiles,
        ));

        Assert::assertSame([], $offenders, sprintf(
            'These package classes call a facade or a service-locating helper. Take the contract in '
            . 'the constructor instead, or mark a call that cannot be injected with %s and a reason:'
            . "\n  %s",
            self::FACADE_CALL_EXEMPTION,
            implode("\n  ", $offenders),
        ));
    }
}
