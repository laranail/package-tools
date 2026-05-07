<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

/**
 * HasVueAssets - Vue.js asset publishing
 *
 * Enables publishing Vue.js specific assets (components, stores, etc.)
 */
trait HasVueAssets
{
    /**
     * Publish Vue.js components directory
     *
     * @param string $sourceDir Source directory (relative to package root)
     * @param string $targetDir Target directory (relative to resources/js)
     */
    public function publishVueComponentsDirectory(string $sourceDir = 'resources/js/components', string $targetDir = 'components'): static
    {
        $sourcePath = $this->packageBasePath($sourceDir);
        $targetPath = resource_path('js/' . trim($targetDir, '/'));

        if (is_dir($sourcePath)) {
            // Delegate to HasAssetPublisher::publishAssets (multi-arg) — the
            // array-form abstract was removed in Phase 3 to avoid collision.
            $this->publishAssets($sourcePath, $targetPath, false, $this->shortName() . '-vue-components');
        }

        return $this;
    }

    /**
     * Publish Vuex stores
     *
     * @param string $sourceDir Source directory
     * @param string $targetDir Target directory
     */
    public function publishVuexStores(string $sourceDir = 'resources/js/stores', string $targetDir = 'stores'): static
    {
        $sourcePath = $this->packageBasePath($sourceDir);
        $targetPath = resource_path('js/' . trim($targetDir, '/'));

        if (is_dir($sourcePath)) {
            $this->publishAssets($sourcePath, $targetPath, false, $this->shortName() . '-vuex-stores');
        }

        return $this;
    }

    /**
     * Publish Pinia stores
     *
     * @param string $sourceDir Source directory
     * @param string $targetDir Target directory
     */
    public function publishPiniaStores(string $sourceDir = 'resources/js/stores', string $targetDir = 'stores'): static
    {
        return $this->publishVuexStores($sourceDir, $targetDir);
    }

    /**
     * Publish Vue composables
     *
     * @param string $sourceDir Source directory
     * @param string $targetDir Target directory
     */
    public function publishVueComposables(string $sourceDir = 'resources/js/composables', string $targetDir = 'composables'): static
    {
        $sourcePath = $this->packageBasePath($sourceDir);
        $targetPath = resource_path('js/' . trim($targetDir, '/'));

        if (is_dir($sourcePath)) {
            $this->publishAssets($sourcePath, $targetPath, false, $this->shortName() . '-vue-composables');
        }

        return $this;
    }

    /**
     * Publish Vue utilities
     *
     * @param string $sourceDir Source directory
     * @param string $targetDir Target directory
     */
    public function publishVueUtils(string $sourceDir = 'resources/js/utils', string $targetDir = 'utils'): static
    {
        $sourcePath = $this->packageBasePath($sourceDir);
        $targetPath = resource_path('js/' . trim($targetDir, '/'));

        if (is_dir($sourcePath)) {
            $this->publishAssets($sourcePath, $targetPath, false, $this->shortName() . '-vue-utils');
        }

        return $this;
    }

    /**
     * Publish all Vue.js assets
     */
    public function publishAllVueAssets(): static
    {
        $this->publishVueComponentsDirectory()
            ->publishVuexStores()
            ->publishVueComposables()
            ->publishVueUtils();

        return $this;
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
