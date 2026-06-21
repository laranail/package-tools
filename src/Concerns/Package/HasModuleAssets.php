<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Simtabi\Laranail\Package\Tools\Services\Asset\AssetPublisher;

/**
 * Publishes assets for modular packages.
 */
trait HasModuleAssets
{
    protected ?AssetPublisher $moduleAssetPublisher = null;

    /**
     * Publish module assets using conventions
     *
     * @param array<string>|null $types Asset types to publish (null = all)
     */
    public function publishModuleAssets(?array $types = null): static
    {
        $publisher = $this->getModuleAssetPublisher();

        $basePath = $this->packageBasePath();
        $moduleName = $this->shortName();

        $publisher->publishModuleAssets($types, $basePath, $moduleName);

        return $this;
    }

    /**
     * Publish specific module asset type
     *
     * @param string $type Asset type ('js', 'css', 'media', 'vendors')
     */
    public function publishModuleAssetType(string $type): static
    {
        return $this->publishModuleAssets([$type]);
    }

    /**
     * Publish module JavaScript assets
     */
    public function publishModuleJs(): static
    {
        return $this->publishModuleAssetType('js');
    }

    /**
     * Publish module CSS assets
     */
    public function publishModuleCss(): static
    {
        return $this->publishModuleAssetType('css');
    }

    /**
     * Publish module media assets (images, videos, etc.)
     */
    public function publishModuleMedia(): static
    {
        return $this->publishModuleAssetType('media');
    }

    /**
     * Publish module vendor assets (third-party libraries)
     */
    public function publishModuleVendors(): static
    {
        return $this->publishModuleAssetType('vendors');
    }

    /**
     * Publish all module assets
     */
    public function publishAllModuleAssets(): static
    {
        return $this->publishModuleAssets();
    }

    /**
     * Get or create module asset publisher instance
     */
    protected function getModuleAssetPublisher(): AssetPublisher
    {
        if (! $this->moduleAssetPublisher) {
            $this->moduleAssetPublisher = app(AssetPublisher::class);
        }

        return $this->moduleAssetPublisher;
    }

    /**
     * Get package base path
     *
     * @param string $path Optional path to append
     */
    abstract protected function packageBasePath(string $path = ''): string;

    /**
     * Get package short name
     */
    abstract protected function shortName(): string;
}
