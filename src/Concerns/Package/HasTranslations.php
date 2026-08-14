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
     * Register translations, namespaced as vendor-package.
     *
     * The optional alias registers an ADDITIONAL namespace verbatim. Prefer
     * leaving it null: a bare alias re-introduces the collision the namespaced
     * form exists to prevent, because Laravel keeps translation namespaces in a
     * flat map and the second package to claim a key silently replaces the first.
     *
     * @example
     * $package->setName('acme/widget')->hasTranslations();
     * // trans('acme-widget::messages.welcome')
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
     * The `vendor-package` translation namespace (e.g. `laranail-validation`).
     * Vendor is required.
     *
     * The separator is a hyphen, not a slash: `lang/vendor/{namespace}` is a
     * single published directory, so a slash would nest the package's files one
     * level deeper than `vendor:publish` and every consumer's override path
     * expect.
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

        return $this->getDashedNamespace();
    }

    abstract public function getDashedNamespace(): string;
}
