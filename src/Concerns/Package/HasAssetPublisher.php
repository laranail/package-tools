<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Asset publishing with a registry, optional directory cleanup, batch
 * publishing, standard asset types, and groups.
 */
trait HasAssetPublisher
{
    /** @var array<string, array{source: string, destination: string, clean: bool, tag: string|null}> Asset registry */
    protected array $assetRegistry = [];

    /** @var array<string, array<string>> Asset groups registry */
    protected array $assetGroups = [];

    /** @var array<string> Standard asset type mappings */
    protected array $standardAssetTypes = [
        'all' => 'public',
        'js' => 'public/js',
        'css' => 'public/css',
        'images' => 'public/images',
        'media' => 'public/media',
        'fonts' => 'public/fonts',
        'vendors' => 'public/vendors',
    ];

    /**
     * Register assets for publishing with optional cleanup
     *
     * @param string $source Source directory (relative to package root)
     * @param string $destination Destination directory (relative to public)
     * @param bool $cleanBeforePublish Clean destination directory before publishing
     * @param string|null $tag Custom publish tag
     *
     * @example
     * ```php
     * $package->publishAssets('resources/assets', 'vendor/blog');
     * ```
     */
    public function publishAssets(
        string $source,
        string $destination,
        bool $cleanBeforePublish = false,
        ?string $tag = null
    ): static {
        $this->assetRegistry[$source] = [
            'source' => $source,
            'destination' => $destination,
            'clean' => $cleanBeforePublish,
            'tag' => $tag,
        ];

        return $this;
    }

    /**
     * Publish module assets by standard type.
     *
     * Types: 'all', 'js', 'css', 'images', 'media', 'fonts', 'vendors'.
     *
     * @param array<string>|string $types Asset type(s) to publish
     * @param bool $cleanBeforePublish Clean before publishing
     *
     * @example
     * ```php
     * $package->publishModuleAssets(['js', 'css']);
     * ```
     */
    public function publishModuleAssets(
        array|string $types = 'all',
        bool $cleanBeforePublish = false
    ): static {
        $types = is_string($types) ? [$types] : $types;

        foreach ($types as $type) {
            if (! isset($this->standardAssetTypes[$type])) {
                throw new RuntimeException("Unknown asset type '{$type}'. Available types: " . implode(', ', array_keys($this->standardAssetTypes)));
            }

            $sourceDir = $this->standardAssetTypes[$type];
            $destinationDir = $this->generateAssetDestination($type);

            $this->publishAssets(
                source: $sourceDir,
                destination: $destinationDir,
                cleanBeforePublish: $cleanBeforePublish,
                tag: "assets-{$type}"
            );
        }

        return $this;
    }

    /**
     * Publish a named group of assets.
     *
     * @param string $groupName Group name
     * @param array<string, string> $assets Map of source => destination
     * @param bool $cleanBeforePublish Clean before publishing
     *
     * @example
     * ```php
     * $package->publishAssetGroup('frontend', [
     *     'resources/js' => 'vendor/blog/js',
     *     'resources/css' => 'vendor/blog/css',
     * ]);
     * ```
     */
    public function publishAssetGroup(
        string $groupName,
        array $assets,
        bool $cleanBeforePublish = false
    ): static {
        $this->assetGroups[$groupName] = [];

        foreach ($assets as $source => $destination) {
            $assetKey = "{$groupName}:{$source}";

            $this->publishAssets(
                source: $source,
                destination: $destination,
                cleanBeforePublish: $cleanBeforePublish,
                tag: "group-{$groupName}"
            );

            $this->assetGroups[$groupName][] = $assetKey;
        }

        return $this;
    }

    /**
     * Publish multiple asset groups at once
     *
     * @param array<string, array<string, string>> $groups Map of group name => assets
     * @param bool $cleanBeforePublish Clean before publishing
     *
     * @example
     * ```php
     * $package->publishAssetGroups([
     *     'frontend' => [
     *         'resources/js' => 'vendor/blog/js',
     *         'resources/css' => 'vendor/blog/css',
     *     ],
     *     'backend' => [
     *         'resources/admin/js' => 'vendor/blog/admin/js',
     *     ],
     * ]);
     * ```
     */
    public function publishAssetGroups(
        array $groups,
        bool $cleanBeforePublish = false
    ): static {
        foreach ($groups as $groupName => $assets) {
            $this->publishAssetGroup($groupName, $assets, $cleanBeforePublish);
        }

        return $this;
    }

    /**
     * Publish custom assets from a source => destination map.
     *
     * @param array<string, string> $customAssets Map of source => destination
     * @param bool $cleanBeforePublish Clean before publishing
     * @param string|null $tag Custom tag
     *
     * @example
     * ```php
     * $package->publishCustomAssets([
     *     'resources/custom/icons' => 'vendor/blog/icons',
     *     'resources/custom/themes' => 'vendor/blog/themes',
     * ]);
     * ```
     */
    public function publishCustomAssets(
        array $customAssets,
        bool $cleanBeforePublish = false,
        ?string $tag = null
    ): static {
        foreach ($customAssets as $source => $destination) {
            $this->publishAssets(
                source: $source,
                destination: $destination,
                cleanBeforePublish: $cleanBeforePublish,
                tag: $tag ?? 'custom-assets'
            );
        }

        return $this;
    }

    /**
     * Check if an asset should be cleaned before publishing
     *
     * @param string $source Source path
     */
    public function shouldCleanAsset(string $source): bool
    {
        return $this->assetRegistry[$source]['clean'] ?? false;
    }

    /**
     * Clean a specific asset destination directory
     *
     * @param string $source Source path (to lookup destination)
     * @return bool True if cleaned successfully
     */
    public function cleanAsset(string $source): bool
    {
        if (! isset($this->assetRegistry[$source])) {
            return false;
        }

        $destination = public_path($this->assetRegistry[$source]['destination']);

        if (File::isDirectory($destination)) {
            File::deleteDirectory($destination);

            return true;
        }

        return false;
    }

    /**
     * Clean all registered asset destinations
     *
     * @return int Number of directories cleaned
     */
    public function cleanAllAssets(): int
    {
        $cleaned = 0;

        foreach ($this->assetRegistry as $source => $config) {
            if ($config['clean'] && $this->cleanAsset($source)) {
                $cleaned++;
            }
        }

        return $cleaned;
    }

    /**
     * Generate asset destination path based on type
     *
     * @param string $type Asset type
     * @return string Destination path
     */
    protected function generateAssetDestination(string $type): string
    {
        $packageName = $this->getPackageKebabName();

        return match ($type) {
            'all' => "vendor/{$packageName}",
            default => "vendor/{$packageName}/{$type}",
        };
    }

    /**
     * Get all registered assets
     *
     * @return array<string, array{source: string, destination: string, clean: bool, tag: string|null}>
     */
    public function getAssetRegistry(): array
    {
        return $this->assetRegistry;
    }

    /**
     * Get all asset groups
     *
     * @return array<string, array<string>>
     */
    public function getAssetGroups(): array
    {
        return $this->assetGroups;
    }

    /**
     * Get assets by group name
     *
     * @return array<string>
     */
    public function getAssetsByGroup(string $groupName): array
    {
        return $this->assetGroups[$groupName] ?? [];
    }

    /**
     * Filter assets by type
     *
     * @param string $type Asset type
     * @return array<string, array{source: string, destination: string, clean: bool, tag: string|null}>
     */
    public function filterAssetsByType(string $type): array
    {
        return array_filter($this->assetRegistry, fn (array $asset): bool => str_contains($asset['tag'] ?? '', "assets-{$type}"));
    }

    /**
     * Check if asset type exists
     */
    public function hasAssetType(string $type): bool
    {
        return isset($this->standardAssetTypes[$type]);
    }

    /**
     * Get all standard asset types
     *
     * @return array<string>
     */
    public function getStandardAssetTypes(): array
    {
        return array_keys($this->standardAssetTypes);
    }

    /**
     * Clear asset registry
     */
    public function clearAssetRegistry(): static
    {
        $this->assetRegistry = [];
        $this->assetGroups = [];

        return $this;
    }

    /**
     * Get package name in kebab-case
     */
    abstract protected function getPackageKebabName(): string;
}
