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

            // Use File facade to check if file exists
            if (! File::isFile($vendorConfig)) {
                continue;
            }

            // Use namespaced key if namespace is set (e.g., 'vendor.package')
            // Otherwise use plain key (e.g., 'package')
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

            // Check for .php file first, then .php.stub
            if (File::isFile($phpConfig = $this->package->basePath(Package::CONFIG_DIR . "/{$configFileName}.php"))) {
                $vendorConfig = $phpConfig;
            } elseif (File::isFile($stubConfig = $this->package->basePath(Package::CONFIG_DIR . "/{$configFileName}.php.stub"))) {
                $vendorConfig = $stubConfig;
            }

            if ($vendorConfig === null) {
                continue;
            }

            // Use namespaced publish tag (e.g., 'vendor::package-config')
            $publishTag = $this->package->getNamespacedPublishTag('config');

            $this->publishes(
                [$vendorConfig => config_path("{$configFileName}.php")],
                $publishTag
            );
        }

        return $this;
    }
}
