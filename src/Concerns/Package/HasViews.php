<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use RuntimeException;

trait HasViews
{
    public bool $hasViews = false;

    public ?string $viewNamespace = null;

    /**
     * Register views. Defaults the namespace to the package short name.
     *
     * @param string|null $namespace Custom view namespace (overrides the default)
     *
     * @example
     * $package->setName('acme/widget')->hasViews();
     * // view('widget::view-name')
     */
    public function hasViews(?string $namespace = null): static
    {
        $this->hasViews = true;

        // Default to the package short name, e.g. setName('vendor/my-package')
        // gives viewNamespace 'my-package'. Matches Spatie's behaviour.
        $this->viewNamespace = $namespace ?? ($this->name !== '' ? $this->name : null);

        return $this;
    }

    /**
     * The view namespace. A custom namespace wins; otherwise vendor/package,
     * which requires a vendor.
     *
     * @throws RuntimeException If vendor is not set
     */
    public function viewNamespace(): string
    {
        if ($this->viewNamespace !== null) {
            return $this->viewNamespace;
        }

        if ($this->configVendor === null) {
            throw new RuntimeException(
                'View namespace requires vendor/package format. ' .
                'Please use $package->setName("vendor/package") instead of just "package".'
            );
        }

        return $this->configVendor . '/' . $this->name;
    }
}
