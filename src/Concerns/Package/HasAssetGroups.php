<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

use Simtabi\Laranail\PackageTools\Services\Asset\AssetGroupResolver;

/**
 * Publishes assets in named groups.
 */
trait HasAssetGroups
{
    protected ?AssetGroupResolver $assetGroupResolver = null;

    protected array $assetGroups = [];

    /**
     * Register asset group for publishing
     *
     * @param string $groupName Group name (e.g., 'js', 'css', 'images')
     * @param array $config Group configuration ['source' => '...', 'target' => '...']
     */
    public function hasAssetGroup(string $groupName, array $config = []): static
    {
        $resolver = $this->getAssetGroupResolver();

        $resolvedConfig = $resolver->resolve($groupName, $config);

        $this->assetGroups[$groupName] = $resolvedConfig;

        // The group config is now stored in $this->assetGroups, which
        // PackageServiceProvider's ProcessAssets iterates at boot. No separate
        // publish call is needed.

        return $this;
    }

    /**
     * Register multiple asset groups
     *
     * @param array<string, array> $groups [groupName => config]
     */
    public function hasAssetGroups(array $groups): static
    {
        foreach ($groups as $groupName => $config) {
            $this->hasAssetGroup($groupName, $config);
        }

        return $this;
    }

    /**
     * Register standard asset groups (js, css, images, fonts)
     */
    public function hasStandardAssetGroups(): static
    {
        $standardGroups = [
            'js' => ['source' => 'js', 'target' => 'js'],
            'css' => ['source' => 'css', 'target' => 'css'],
            'images' => ['source' => 'images', 'target' => 'images'],
            'fonts' => ['source' => 'fonts', 'target' => 'fonts'],
        ];

        return $this->hasAssetGroups($standardGroups);
    }

    /**
     * Register asset group with custom publish path
     *
     * @param string $groupName Group name
     * @param string $sourcePath Source path relative to package public directory
     * @param string $targetPath Target path relative to public directory
     */
    public function hasCustomAssetGroup(string $groupName, string $sourcePath, string $targetPath): static
    {
        return $this->hasAssetGroup($groupName, [
            'source' => $sourcePath,
            'target' => $targetPath,
        ]);
    }

    /**
     * Get registered asset groups
     *
     * @return array<string, array>
     */
    public function getAssetGroups(): array
    {
        return $this->assetGroups;
    }

    /**
     * Get or create asset group resolver instance
     */
    protected function getAssetGroupResolver(): AssetGroupResolver
    {
        if (! $this->assetGroupResolver) {
            $packageBasePath = $this->packageBasePath();
            $publishPath = 'vendor/' . $this->shortName();

            $this->assetGroupResolver = new AssetGroupResolver($packageBasePath, $publishPath);
        }

        return $this->assetGroupResolver;
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
