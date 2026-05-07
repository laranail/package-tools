<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

use RuntimeException;

trait HasViews
{
    public bool $hasViews = false;

    public ?string $viewNamespace = null;

    /**
     * Register views with optional custom namespace
     *
     * If no namespace is provided, uses vendor/package format when vendor exists,
     * otherwise falls back to package name.
     *
     * @param string|null $namespace Custom view namespace (overrides auto-generated)
     *
     * @example
     * // With vendor/package format
     * $package->setName('acme/widget');
     * $package->hasViews();
     * // View namespace: 'acme/widget'
     * // Access: view('acme/widget::view-name')
     *
     * // Custom namespace
     * $package->hasViews('custom.namespace');
     * // View namespace: 'custom.namespace'
     */
    public function hasViews(?string $namespace = null): static
    {
        $this->hasViews = true;

        // When no explicit namespace given, default to the package short
        // name (e.g. setName('vendor/my-package') → viewNamespace='my-package').
        // Tests assert this default; also matches Spatie's behaviour.
        $this->viewNamespace = $namespace ?? ($this->name !== '' ? $this->name : null);

        return $this;
    }

    /**
     * Get the view namespace
     *
     * Returns vendor/package format. Custom namespace takes precedence if set.
     * Vendor is REQUIRED - no fallback to package name.
     *
     * @throws RuntimeException If vendor is not set
     */
    public function viewNamespace(): string
    {
        // Custom namespace takes precedence
        if ($this->viewNamespace !== null) {
            return $this->viewNamespace;
        }

        // Vendor is REQUIRED - no legacy support
        if (! property_exists($this, 'configVendor') || $this->configVendor === null) {
            throw new RuntimeException(
                'View namespace requires vendor/package format. ' .
                'Please use $package->setName("vendor/package") instead of just "package".'
            );
        }

        // Return vendor/package format
        return $this->configVendor . '/' . $this->name;
    }
}
