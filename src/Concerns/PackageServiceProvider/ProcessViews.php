<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider;

trait ProcessViews
{
    protected function bootPackageViews(): self
    {
        if (! $this->package->hasViews) {
            return $this;
        }

        $viewNamespace = $this->package->viewNamespace();
        $viewsPath = $this->package->basePath('/resources/views');
        $vendorViews = realpath($viewsPath) ?: $viewsPath;
        $appViews = base_path("resources/views/vendor/{$viewNamespace}");

        $this->loadViewsFrom($vendorViews, $viewNamespace);

        if ($this->app->runningInConsole()) {
            $publishTag = method_exists($this->package, 'getNamespacedPublishTag')
                ? $this->package->getNamespacedPublishTag('views')
                : "{$viewNamespace}-views";

            $this->publishes([$vendorViews => $appViews], $publishTag);
        }

        return $this;
    }
}
