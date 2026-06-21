<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use RuntimeException;

trait HasTranslations
{
    public bool $hasTranslations = false;

    /**
     * Register translations, namespaced as vendor/package.
     *
     * @example
     * $package->setName('acme/widget')->hasTranslations();
     * // trans('acme/widget::messages.welcome')
     */
    public function hasTranslations(): static
    {
        $this->hasTranslations = true;

        return $this;
    }

    /**
     * The vendor/package translation namespace. Vendor is required.
     *
     * @throws RuntimeException If vendor is not set
     */
    public function translationNamespace(): string
    {
        if (! property_exists($this, 'configVendor') || $this->configVendor === null) {
            throw new RuntimeException(
                'Translation namespace requires vendor/package format. ' .
                'Please use $package->setName("vendor/package") instead of just "package".'
            );
        }

        return $this->configVendor . '/' . $this->name;
    }
}
