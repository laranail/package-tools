<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\PackageServiceProvider;

/**
 * LoadsHelpers - Automatically loads helper functions
 *
 * This trait ensures helper functions are loaded during package registration.
 * Helper files are loaded once and cached to prevent redeclaration errors.
 */
trait LoadsHelpers
{
    /**
     * Load helper functions if helpers directory exists
     *
     * This method is automatically called during package registration.
     * It will load all .php files from the helpers/ directory.
     */
    protected function loadPackageHelpers(): static
    {
        // Auto-load helpers if directory exists
        $this->package->loadHelpers();

        return $this;
    }
}
