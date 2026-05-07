<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\PackageServiceProvider;

trait ProcessAssets
{
    protected function bootPackageAssets(): static
    {
        if (! $this->package->hasAssets || ! $this->app->runningInConsole()) {
            return $this;
        }

        $vendorAssets = $this->package->basePath('/../resources/dist');
        $appAssets = public_path("vendor/{$this->package->shortName()}");

        // Use namespaced publish tag: vendor::package-assets
        $publishTag = method_exists($this->package, 'getNamespacedPublishTag')
            ? $this->package->getNamespacedPublishTag('assets')
            : "{$this->package->shortName()}-assets";

        $this->publishes([$vendorAssets => $appAssets], $publishTag);

        return $this;
    }
}
