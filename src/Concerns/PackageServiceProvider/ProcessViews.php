<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\PackageServiceProvider;

trait ProcessViews
{
    protected function bootPackageViews(): self
    {
        if (! $this->package->hasViews) {
            return $this;
        }

        // Get namespaced view namespace (vendor/package or package)
        $viewNamespace = $this->package->viewNamespace();
        $viewsPath = $this->package->basePath('/resources/views');
        $vendorViews = realpath($viewsPath) ?: $viewsPath;
        $appViews = base_path("resources/views/vendor/{$viewNamespace}");

        // Load views with vendor/package namespace
        $this->loadViewsFrom($vendorViews, $viewNamespace);

        if ($this->app->runningInConsole()) {
            // Use namespaced publish tag: vendor::package-views
            $publishTag = method_exists($this->package, 'getNamespacedPublishTag')
                ? $this->package->getNamespacedPublishTag('views')
                : "{$viewNamespace}-views";

            $this->publishes([$vendorViews => $appViews], $publishTag);
        }

        return $this;
    }
}
