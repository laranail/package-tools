<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

use Simtabi\Laranail\PackageTools\Services\Asset\AssetRegistry;

/**
 * HasAssetCleanup - Asset cleanup support
 *
 * Enables cleaning up old assets before publishing new ones
 */
trait HasAssetCleanup
{
    protected ?AssetRegistry $assetCleanupRegistry = null;

    protected bool $enableAssetCleanup = false;

    /**
     * Enable automatic asset cleanup before publishing
     *
     * @param bool $enabled Enable cleanup
     */
    public function withAssetCleanup(bool $enabled = true): static
    {
        $this->enableAssetCleanup = $enabled;

        return $this;
    }

    /**
     * Register asset for cleanup
     *
     * @param string $targetPath Target path to clean
     * @param string $tag Publish tag
     */
    public function registerAssetForCleanup(string $targetPath, string $tag): static
    {
        $registry = $this->getAssetCleanupRegistry();
        $registry->register($tag, $targetPath, true);

        return $this;
    }

    /**
     * Clean up assets by tag
     *
     * @param string $tag Publish tag
     */
    public function cleanupAssets(string $tag): bool
    {
        $registry = $this->getAssetCleanupRegistry();

        if ($registry->has($tag)) {
            return $registry->cleanup($tag);
        }

        return false;
    }

    /**
     * Clean up all registered assets
     *
     * @return array<string, bool> [tag => success]
     */
    public function cleanupAllAssets(): array
    {
        $registry = $this->getAssetCleanupRegistry();
        $results = [];

        foreach ($registry->all() as $tag => $path) {
            $results[$tag] = $registry->cleanup($tag);
        }

        return $results;
    }

    /**
     * Publish assets with automatic cleanup
     *
     * @param string $sourcePath Source path
     * @param string $targetPath Target path
     * @param string $tag Publish tag
     */
    public function publishAssetsWithCleanup(string $sourcePath, string $targetPath, string $tag): static
    {
        if ($this->enableAssetCleanup) {
            $this->registerAssetForCleanup($targetPath, $tag);
            $this->cleanupAssets($tag);
        }

        // Delegate to HasAssetPublisher::publishAssets (the canonical
        // multi-arg form). The array-form abstract previously declared here
        // collided with HasAssetPublisher under the ConfiguresAssets aggregator
        // — see ADR-004.
        $this->publishAssets($sourcePath, $targetPath, false, $tag);

        return $this;
    }

    /**
     * Check if asset cleanup is enabled
     */
    public function isAssetCleanupEnabled(): bool
    {
        return $this->enableAssetCleanup;
    }

    /**
     * Get or create asset cleanup registry instance
     */
    protected function getAssetCleanupRegistry(): AssetRegistry
    {
        if (! $this->assetCleanupRegistry) {
            $this->assetCleanupRegistry = app(AssetRegistry::class);
        }

        return $this->assetCleanupRegistry;
    }
}
