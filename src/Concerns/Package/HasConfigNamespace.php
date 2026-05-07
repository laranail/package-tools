<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

use Illuminate\Support\Str;
use RuntimeException;

/**
 * HasConfigNamespace - Auto-namespacing for config files
 *
 * **Problem:**
 * Standard package approach creates top-level config keys which can collide
 * with other packages or application config files.
 *
 * **Solution:**
 * This trait enables automatic namespacing based on vendor/package format:
 * - `config('vendor.package')` instead of `config('package')`
 * - Prevents naming collisions across packages
 * - Maintains clean organization
 *
 * **Usage:**
 * ```php
 * // Vendor/package format is REQUIRED
 * $package->setName('vendor/package-name');
 * // Config: config('vendor.package-name')
 * // Publish: vendor::package-name-config
 * ```
 *
 * **BREAKING CHANGE:** Legacy single package name format is no longer supported.
 * You must use `vendor/package` format. This ensures proper namespacing and prevents collisions.
 *
 * **Benefits:**
 * - Prevents config key collisions
 * - Maintains vendor organization
 * - Enforces consistent naming across ecosystem
 * - Provides multiple format helpers for flexibility
 */
trait HasConfigNamespace
{
    public ?string $configVendor = null;

    /**
     * Get the full namespaced config key
     *
     * If vendor is set, returns: 'vendor.configname'
     * Otherwise returns: 'configname'
     *
     * Uses getDottedNamespace() internally for consistent config key generation.
     */
    public function getNamespacedConfigKey(string $configFileName): string
    {
        // Use getDottedNamespace() for consistency
        return $this->getDottedNamespace();
    }

    /**
     * Check if config namespacing is enabled
     */
    public function hasConfigNamespacing(): bool
    {
        return $this->configVendor !== null;
    }

    /**
     * Get the config vendor/namespace
     */
    public function getConfigVendor(): ?string
    {
        return $this->configVendor;
    }

    /**
     * Get dotted namespace: 'vendor.package'
     *
     * Useful for config keys, event names, or any dot-separated identifiers.
     * Vendor is REQUIRED - no legacy support.
     *
     * @throws RuntimeException If vendor is not set
     *
     * @example
     * $package->name('acme/widget');
     * $package->getDottedNamespace(); // 'acme.widget'
     */
    public function getDottedNamespace(): string
    {
        if ($this->configVendor === null) {
            throw new RuntimeException(
                'Dotted namespace requires vendor/package format. ' .
                'Please use $package->setName("vendor/package") instead of just "package".'
            );
        }

        return Str::of($this->configVendor)
            ->append('.', $this->name)
            ->toString();
    }

    /**
     * Get dashed namespace: 'vendor-package'
     *
     * Useful for CSS classes, cache keys, or file names.
     * Vendor is REQUIRED - no legacy support.
     *
     * @throws RuntimeException If vendor is not set
     *
     * @example
     * $package->name('acme/widget');
     * $package->getDashedNamespace(); // 'acme-widget'
     */
    public function getDashedNamespace(): string
    {
        if ($this->configVendor === null) {
            throw new RuntimeException(
                'Dashed namespace requires vendor/package format. ' .
                'Please use $package->setName("vendor/package") instead of just "package".'
            );
        }

        return Str::of($this->configVendor)
            ->append('-', $this->name)
            ->toString();
    }

    /**
     * Get double-colon namespace: 'vendor::package' (Laravel-style)
     *
     * Useful for views, translations, publish tags, or any Laravel namespace convention.
     * Vendor is REQUIRED - no legacy support.
     *
     * @throws RuntimeException If vendor is not set
     *
     * @example
     * $package->name('acme/widget');
     * $package->getDoubleColonNamespace(); // 'acme::widget'
     */
    public function getDoubleColonNamespace(): string
    {
        if ($this->configVendor === null) {
            throw new RuntimeException(
                'Double-colon namespace requires vendor/package format. ' .
                'Please use $package->setName("vendor/package") instead of just "package".'
            );
        }

        return Str::of($this->configVendor)
            ->append('::', $this->name)
            ->toString();
    }

    /**
     * Get slash namespace: 'vendor/package' (Composer-style)
     *
     * Useful for Composer package references, URLs, or path-like identifiers.
     * Vendor is REQUIRED - no legacy support.
     *
     * @throws RuntimeException If vendor is not set
     *
     * @example
     * $package->name('acme/widget');
     * $package->getSlashNamespace(); // 'acme/widget'
     */
    public function getSlashNamespace(): string
    {
        if ($this->configVendor === null) {
            throw new RuntimeException(
                'Slash namespace requires vendor/package format. ' .
                'Please use $package->setName("vendor/package") instead of just "package".'
            );
        }

        return Str::of($this->configVendor)
            ->append('/', $this->name)
            ->toString();
    }

    /**
     * Build custom namespace with any separator
     *
     * Provides maximum flexibility for custom naming conventions.
     * Vendor is REQUIRED - no legacy support.
     *
     * @param string $separator Custom separator (can be any string)
     *
     * @throws RuntimeException If vendor is not set
     *
     * @example
     * $package->name('acme/widget');
     * $package->buildNamespace('|');   // 'acme|widget'
     * $package->buildNamespace('->');  // 'acme->widget'
     * $package->buildNamespace(' | '); // 'acme | widget'
     */
    public function buildNamespace(string $separator): string
    {
        if ($this->configVendor === null) {
            throw new RuntimeException(
                'Custom namespace requires vendor/package format. ' .
                'Please use $package->setName("vendor/package") instead of just "package".'
            );
        }

        return Str::of($this->configVendor)
            ->append($separator, $this->name)
            ->toString();
    }

    /**
     * Get the namespaced publish tag for configs
     *
     * Returns Laravel-style publish tag with vendor namespace.
     * Uses `::` separator following Laravel conventions.
     *
     * @param string $suffix Tag suffix (default: 'config')
     *
     * @example
     * // With vendor
     * $package->name('acme/widget');
     * $package->getNamespacedPublishTag('config');
     * // Returns: 'acme::widget-config'
     *
     * // Without vendor
     * $package->name('my-package');
     * $package->getNamespacedPublishTag('config');
     * // Returns: 'my-package-config'
     *
     * // Custom suffix
     * $package->getNamespacedPublishTag('migrations');
     * // Returns: 'acme::widget-migrations'
     */
    public function getNamespacedPublishTag(string $suffix = 'config'): string
    {
        // Use getDoubleColonNamespace() for Laravel-style namespacing
        return Str::of($this->getDoubleColonNamespace())
            ->append('-', $suffix)
            ->toString();
    }
}
