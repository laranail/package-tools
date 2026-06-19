<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

/**
 * Middleware domain aggregator.
 */
trait ConfiguresMiddleware
{
    use HasEnhancedMiddleware;
    use HasMiddlewareManagement;
}
