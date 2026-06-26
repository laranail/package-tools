<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider;

use Illuminate\Support\Facades\File;

trait ProcessAssets
{
    protected function bootPackageAssets(): static
    {
        if (! $this->package->hasAssets || ! $this->app->runningInConsole()) {
            return $this;
        }

        $vendorAssets = $this->package->basePath('/resources/dist');
        $appAssets = public_path("vendor/{$this->package->shortName()}");

        $publishTag = $this->package->getNamespacedPublishTag('assets');

        $this->publishes([$vendorAssets => $appAssets], $publishTag);

        $this->bootPackageAssetRegistry();
        $this->bootPackageDeclaredAssetGroups();

        return $this;
    }

    /**
     * Publish every entry registered in the package asset registry.
     */
    protected function bootPackageAssetRegistry(): void
    {
        foreach ($this->package->getAssetRegistry() as $entry) {
            $source = $this->package->basePath('/' . $entry['source']);
            $destination = public_path($entry['destination']);

            if (($entry['clean'] ?? false) && File::isDirectory($destination)) {
                File::deleteDirectory($destination);
            }

            $tag = $entry['tag'] ?? "{$this->package->shortName()}-assets";

            $this->publishes([$source => $destination], $tag);
        }
    }

    /**
     * Publish every declared asset group whose source directory exists.
     */
    protected function bootPackageDeclaredAssetGroups(): void
    {
        foreach ($this->package->getDeclaredAssetGroups() as $name => $group) {
            $source = $this->package->basePath('/' . $group['source']);

            if (! File::isDirectory($source)) {
                continue;
            }

            $target = public_path($group['target']);

            $this->publishes([$source => $target], "{$this->package->shortName()}-{$name}");
        }
    }
}
