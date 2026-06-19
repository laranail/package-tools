<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Events;

use Throwable;

/**
 * Dispatched when an individual seeder throws.
 */
final readonly class SeederFailed
{
    public function __construct(
        public string $seederClass,
        public Throwable $exception,
        public ?string $package = null,
    ) {}
}
