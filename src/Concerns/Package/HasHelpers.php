<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

use Illuminate\Support\Facades\File;

/**
 * HasHelpers - Auto-loading support for helper functions
 *
 * Automatically loads all PHP files from the helpers directory if it exists.
 * Helper files should contain global function definitions.
 *
 * **Directory Structure:**
 * ```
 * package-root/
 * └── helpers/
 *     ├── helpers.php
 *     ├── string-helpers.php
 *     └── array-helpers.php
 * ```
 *
 * **Usage:**
 * Helper files are automatically loaded when the package is registered.
 * No manual configuration needed - just place helpers in the helpers/ directory.
 */
trait HasHelpers
{
    /** @var bool Whether helpers have been loaded */
    protected bool $helpersLoaded = false;

    /**
     * Auto-load all helper files from the helpers directory
     *
     * Scans the helpers/ directory and requires all .php files.
     * Files are loaded once to prevent redeclaration errors.
     */
    public function loadHelpers(): static
    {
        if ($this->helpersLoaded) {
            return $this;
        }

        $helpersPath = $this->basePath(self::HELPERS_DIR);

        // Check if helpers directory exists
        if (! File::isDirectory($helpersPath)) {
            return $this;
        }

        // Load all PHP files from helpers directory using File facade
        $helperFiles = File::glob($helpersPath . '/*.php');

        foreach ($helperFiles as $helperFile) {
            if (File::isFile($helperFile)) {
                File::requireOnce($helperFile);
            }
        }

        $this->helpersLoaded = true;

        return $this;
    }

    /**
     * Check if helpers directory exists
     */
    public function hasHelpersDirectory(): bool
    {
        $helpersPath = $this->basePath(self::HELPERS_DIR);

        return File::isDirectory($helpersPath);
    }

    /**
     * Get list of helper files
     */
    public function getHelperFiles(): array
    {
        $helpersPath = $this->basePath(self::HELPERS_DIR);

        if (! File::isDirectory($helpersPath)) {
            return [];
        }

        return File::glob($helpersPath . '/*.php');
    }
}
