<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

use RuntimeException;

trait HasTranslations
{
    public bool $hasTranslations = false;

    /**
     * Register translations
     *
     * Translations are automatically namespaced using vendor/package format
     * when vendor exists, otherwise uses package name.
     *
     *
     * @example
     * // With vendor/package format
     * $package->setName('acme/widget');
     * $package->hasTranslations();
     * // Translation namespace: 'acme/widget'
     * // Access: trans('acme/widget::messages.welcome')
     *
     * // Without vendor
     * $package->setName('widget');
     * $package->hasTranslations();
     * // Translation namespace: 'widget'
     * // Access: trans('widget::messages.welcome')
     */
    public function hasTranslations(): static
    {
        $this->hasTranslations = true;

        return $this;
    }

    /**
     * Get the translation namespace
     *
     * Returns vendor/package format. Vendor is REQUIRED - no fallback.
     *
     * @throws RuntimeException If vendor is not set
     */
    public function translationNamespace(): string
    {
        // Vendor is REQUIRED - no legacy support
        if (! property_exists($this, 'configVendor') || $this->configVendor === null) {
            throw new RuntimeException(
                'Translation namespace requires vendor/package format. ' .
                'Please use $package->setName("vendor/package") instead of just "package".'
            );
        }

        // Return vendor/package format
        return $this->configVendor . '/' . $this->name;
    }
}
