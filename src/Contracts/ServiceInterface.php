<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Contracts;

/**
 * Base Service Interface
 *
 * All service classes should implement this interface to ensure
 * consistency and testability across the package.
 */
interface ServiceInterface
{
    /**
     * Check if the service is properly initialized and ready to use
     */
    public function isReady(): bool;

    /**
     * Get the service name identifier
     */
    public function getName(): string;
}
