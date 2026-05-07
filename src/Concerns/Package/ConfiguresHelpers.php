<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

/**
 * ConfiguresHelpers — domain aggregator (ADR-004).
 *
 * Wraps the single helpers leaf trait.
 */
trait ConfiguresHelpers
{
    use HasHelpers;
}
