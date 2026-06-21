<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Support;

use Simtabi\Laranail\Package\Tools\Support\RuntimeConfigurator;

/**
 * Regression coverage for RuntimeConfigurator::formatBytes(). The power
 * exponent used to index the units table is derived from floor(log(...)),
 * which yields a float; indexing an array with a float key is invalid in
 * PHP 8+ (deprecation/truncation). The exponent must be an int.
 */
it('formats byte counts across unit boundaries with an integer exponent', function (): void {
    expect(RuntimeConfigurator::formatBytes(0))->toBe('0 B')
        ->and(RuntimeConfigurator::formatBytes(512))->toBe('512 B')
        ->and(RuntimeConfigurator::formatBytes(1024))->toBe('1 KB')
        ->and(RuntimeConfigurator::formatBytes(1048576))->toBe('1 MB')
        ->and(RuntimeConfigurator::formatBytes(1073741824))->toBe('1 GB')
        ->and(RuntimeConfigurator::formatBytes(1536, 0))->toBe('2 KB');
});

it('caps the unit at the largest known unit', function (): void {
    // Far beyond TB; exponent is clamped so the index stays valid.
    expect(RuntimeConfigurator::formatBytes(2 ** 60))->toContain('TB');
});
