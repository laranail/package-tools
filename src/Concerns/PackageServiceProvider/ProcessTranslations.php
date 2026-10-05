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

        $translationNamespace = $this->package->translationNamespace();

        $vendorTranslations = $this->package->basePath('/resources/lang');
        $appTranslations = (function_exists('lang_path'))
            ? lang_path("vendor/{$translationNamespace}")
            : resource_path("lang/vendor/{$translationNamespace}");

        // The canonical `vendor/package`, its `vendor-package` alias, and the optional declared
        // alias (e.g. 'license-kit::'), all over the same packaged files.
        foreach ($this->package->translationNamespaces() as $namespace) {
            $this->loadTranslationsFrom($vendorTranslations, $namespace);
        }

        $this->loadJsonTranslationsFrom($vendorTranslations);
        $this->loadJsonTranslationsFrom($appTranslations);

        if ($this->app->runningInConsole()) {
            $publishTag = $this->package->getNamespacedPublishTag('translations');

            $this->publishes(
                [$vendorTranslations => $appTranslations],
                $publishTag,
            );
        }

        return $this;
    }
}
