<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\PackageServiceProvider;

use Illuminate\Support\Facades\File;
use Simtabi\Laranail\PackageTools\Package;

trait ProcessConfigs
{
    public function registerPackageConfigs(): self
    {
        if (empty($this->package->configFileNames)) {
            return $this;
        }

        foreach ($this->package->configFileNames as $configFileName) {
            $vendorConfig = $this->package->basePath(Package::CONFIG_DIR . "/{$configFileName}.php");

            if (! File::isFile($vendorConfig)) {
                continue;
            }

            // Namespaced key (vendor.package) when namespacing is on, else plain.
            $configKey = $this->package->hasConfigNamespacing()
                ? $this->package->getNamespacedConfigKey($configFileName)
                : $configFileName;

            $this->mergeConfigFrom($vendorConfig, $configKey);
        }

        return $this;
    }

    protected function bootPackageConfigs(): self
    {
        if (empty($this->package->configFileNames) || ! $this->app->runningInConsole()) {
            return $this;
        }

        foreach ($this->package->configFileNames as $configFileName) {
            $vendorConfig = null;

            // Prefer .php, fall back to .php.stub.
            if (File::isFile($phpConfig = $this->package->basePath(Package::CONFIG_DIR . "/{$configFileName}.php"))) {
                $vendorConfig = $phpConfig;
            } elseif (File::isFile($stubConfig = $this->package->basePath(Package::CONFIG_DIR . "/{$configFileName}.php.stub"))) {
                $vendorConfig = $stubConfig;
            }

            if ($vendorConfig === null) {
                continue;
            }

            $publishTag = $this->package->getNamespacedPublishTag('config');

            $this->publishes(
                [$vendorConfig => config_path("{$configFileName}.php")],
                $publishTag
            );
        }

        return $this;
    }
}
