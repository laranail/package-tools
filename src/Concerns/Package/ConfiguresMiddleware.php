<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

/**
 * ConfiguresMiddleware — domain aggregator (ADR-004).
 */
trait ConfiguresMiddleware
{
    use HasEnhancedMiddleware;
    use HasMiddlewareManagement;
}
