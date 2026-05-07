<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\PackageServiceProvider;

trait ProcessBladeComponents
{
    protected function bootPackageBladeComponents(): self
    {
        if (empty($this->package->viewComponents)) {
            return $this;
        }

        foreach ($this->package->viewComponents as $componentClass => $prefix) {
            $this->loadViewComponentsAs($prefix, [$componentClass]);
        }

        if ($this->app->runningInConsole()) {
            $vendorComponents = $this->package->basePath('/Components');
            $appComponents = base_path("app/View/Components/vendor/{$this->package->shortName()}");

            // Use namespaced publish tag: vendor::package-components
            $publishTag = method_exists($this->package, 'getNamespacedPublishTag')
                ? $this->package->getNamespacedPublishTag('components')
                : "{$this->package->shortName()}-components";

            $this->publishes([$vendorComponents => $appComponents], $publishTag);
        }

        return $this;
    }
}
