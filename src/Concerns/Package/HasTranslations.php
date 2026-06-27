<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use RuntimeException;

trait HasTranslations
{
    public bool $hasTranslations = false;

    /**
     * Optional short namespace alias registered alongside the full
     * vendor/package one (e.g. 'license-kit' → trans('license-kit::file.key')).
     */
    public ?string $translationAlias = null;

    /**
     * Register translations, namespaced as vendor/package. Pass an optional
     * short alias to ALSO register a bare namespace so keys read shorter.
     *
     * @example
     * $package->setName('acme/widget')->hasTranslations('widget');
     * // trans('acme/widget::messages.welcome') AND trans('widget::messages.welcome')
     */
    public function hasTranslations(?string $alias = null): static
    {
        $this->hasTranslations = true;
        $this->translationAlias = $alias;

        return $this;
    }

    public function getTranslationAlias(): ?string
    {
        return $this->translationAlias;
    }

    /**
     * The vendor/package translation namespace. Vendor is required.
     *
     * @throws RuntimeException If vendor is not set
     */
    public function translationNamespace(): string
    {
        if ($this->configVendor === null) {
            throw new RuntimeException(
                'Translation namespace requires vendor/package format. ' .
                'Please use $package->setName("vendor/package") instead of just "package".'
            );
        }

        return $this->configVendor . '/' . $this->name;
    }
}
