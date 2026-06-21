<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider;

trait ProcessTranslations
{
    protected function bootPackageTranslations(): self
    {
        if (! $this->package->hasTranslations) {
            return $this;
        }

        $translationNamespace = method_exists($this->package, 'translationNamespace')
            ? $this->package->translationNamespace()
            : $this->package->shortName();

        $vendorTranslations = $this->package->basePath('/resources/lang');
        $appTranslations = (function_exists('lang_path'))
            ? lang_path("vendor/{$translationNamespace}")
            : resource_path("lang/vendor/{$translationNamespace}");

        $this->loadTranslationsFrom($vendorTranslations, $translationNamespace);

        $this->loadJsonTranslationsFrom($vendorTranslations);
        $this->loadJsonTranslationsFrom($appTranslations);

        if ($this->app->runningInConsole()) {
            $publishTag = method_exists($this->package, 'getNamespacedPublishTag')
                ? $this->package->getNamespacedPublishTag('translations')
                : "{$translationNamespace}-translations";

            $this->publishes(
                [$vendorTranslations => $appTranslations],
                $publishTag
            );
        }

        return $this;
    }
}
