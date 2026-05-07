<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

/**
 * ConfiguresServiceProviders — domain aggregator (ADR-004).
 *
 * Wraps the single service-providers leaf trait.
 */
trait ConfiguresServiceProviders
{
    use HasServiceProviders;
}
