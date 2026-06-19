<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

use Illuminate\Support\Str;
use RuntimeException;

/**
 * Auto-namespaces config files by vendor/package so keys like
 * `config('vendor.package')` don't collide with other packages.
 *
 * The `vendor/package` name format is required; a bare package name is
 * not supported.
 *
 * ```php
 * $package->setName('vendor/package-name');
 * // Config:  config('vendor.package-name')
 * // Publish: vendor::package-name-config
 * ```
 */
trait HasConfigNamespace
{
    public ?string $configVendor = null;

    /**
     * Get the full namespaced config key, e.g. 'vendor.package'.
     */
    public function getNamespacedConfigKey(string $configFileName): string
    {
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
     * Dotted namespace 'vendor.package', for config keys, event names,
     * or other dot-separated identifiers. Vendor is required.
     *
     * @throws RuntimeException If vendor is not set
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
     * Dashed namespace 'vendor-package', for CSS classes, cache keys,
     * or file names. Vendor is required.
     *
     * @throws RuntimeException If vendor is not set
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
     * Laravel-style namespace 'vendor::package', for views, translations,
     * and publish tags. Vendor is required.
     *
     * @throws RuntimeException If vendor is not set
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
     * Composer-style namespace 'vendor/package', for package references,
     * URLs, or path-like identifiers. Vendor is required.
     *
     * @throws RuntimeException If vendor is not set
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
     * Build a namespace with any separator. Vendor is required.
     *
     * @param string $separator Custom separator (can be any string)
     *
     * @throws RuntimeException If vendor is not set
     *
     * @example
     * $package->buildNamespace('|'); // 'acme|widget'
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
     * Laravel-style publish tag with the vendor namespace, e.g.
     * 'acme::widget-config'.
     *
     * @param string $suffix Tag suffix (default: 'config')
     */
    public function getNamespacedPublishTag(string $suffix = 'config'): string
    {
        return Str::of($this->getDoubleColonNamespace())
            ->append('-', $suffix)
            ->toString();
    }
}
