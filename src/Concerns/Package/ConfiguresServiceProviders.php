<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

/**
 * Service-providers domain aggregator. Wraps the single leaf trait.
 */
trait ConfiguresServiceProviders
{
    use HasServiceProviders;
}
