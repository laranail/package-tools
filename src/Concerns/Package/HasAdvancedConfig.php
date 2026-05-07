<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

use Illuminate\Support\Facades\Config;
use RuntimeException;

/**
 * HasAdvancedConfig - Advanced configuration management for packages
 *
 * Provides sophisticated config handling with:
 * - Deep merge into global config keys (e.g., services.php)
 * - Deep merge support for nested arrays
 * - Safe mode to prevent accidental overwrites
 * - Config validation
 * - Batch operations
 */
trait HasAdvancedConfig
{
    /** @var array<string, array{source: string, target: string, deep: bool}> Merge queue */
    protected array $configMerges = [];

    /** @var bool Safe mode prevents overwriting existing config values */
    protected bool $configSafeMode = true;

    /**
     * Merge package config into a global Laravel config key
     *
     * This allows packages to inject configuration into existing Laravel
     * config files like `services.php`, `auth.php`, `filesystems.php`, etc.
     *
     * @param string $sourceKey Source config key (from package config)
     * @param string $targetKey Target global config key to merge into
     * @param bool $deep Use deep merge (default: true)
     *
     * @example Merge package services into global services.php
     * ```php
     * $package->mergeConfigInto('services', 'services', deep: true);
     * // Package config: config/mypackage.php has ['services' => ['twitter' => [...]]]
     * // Result: Merged into config('services.twitter')
     * ```
     * @example Merge package auth guards
     * ```php
     * $package->mergeConfigInto('auth.guards', 'auth.guards');
     * ```
     */
    public function mergeConfigInto(
        string $sourceKey,
        string $targetKey,
        bool $deep = true
    ): static {
        $this->configMerges[$sourceKey] = [
            'source' => $sourceKey,
            'target' => $targetKey,
            'deep' => $deep,
        ];

        return $this;
    }

    /**
     * Process all queued config merges
     *
     * This method should be called from the service provider's register() method
     * after all config files have been loaded.
     */
    public function processConfigMerges(): void
    {
        foreach ($this->configMerges as $merge) {
            $this->executeMerge(
                $merge['source'],
                $merge['target'],
                $merge['deep']
            );
        }
    }

    /**
     * Execute a single config merge
     *
     * @param string $sourceKey Source key from package config
     * @param string $targetKey Target global config key
     * @param bool $deep Use deep merge
     *
     * @throws RuntimeException If merge validation fails
     */
    protected function executeMerge(string $sourceKey, string $targetKey, bool $deep): void
    {
        // Get package config namespace
        $namespace = $this->getConfigNamespace();

        // Build full source path (e.g., 'mypackage.services')
        $fullSourceKey = $namespace . '.' . $sourceKey;

        // Get source data
        $sourceData = Config::get($fullSourceKey);

        if ($sourceData === null) {
            // Source doesn't exist - skip silently
            return;
        }

        // Get existing target data
        $targetData = Config::get($targetKey, []);

        // Validate safe mode
        if ($this->configSafeMode && ! empty($targetData)) {
            $this->validateSafeMerge($targetData, $sourceData, $targetKey);
        }

        // Perform merge
        $merged = $deep
            ? $this->deepMerge($targetData, $sourceData)
            : array_merge((array) $targetData, (array) $sourceData);

        // Set merged data
        Config::set($targetKey, $merged);
    }

    /**
     * Deep merge two arrays recursively
     *
     * Unlike array_merge_recursive, this properly handles:
     * - Preserving integer keys
     * - Replacing scalar values instead of creating arrays
     * - Handling null values correctly
     *
     * @param array $target Target array
     * @param array $source Source array
     * @return array Merged array
     */
    protected function deepMerge(array $target, array $source): array
    {
        foreach ($source as $key => $value) {
            if (is_array($value) && isset($target[$key]) && is_array($target[$key])) {
                // Both are arrays - recursively merge
                $target[$key] = $this->deepMerge($target[$key], $value);
            } else {
                // One is scalar or target doesn't have this key - replace
                $target[$key] = $value;
            }
        }

        return $target;
    }

    /**
     * Validate safe mode merge
     *
     * Checks if any keys would be overwritten and throws an exception
     * if safe mode is enabled.
     *
     * @param array $target Target config
     * @param array $source Source config
     * @param string $targetKey Target key path (for error messages)
     *
     * @throws RuntimeException If keys would be overwritten
     */
    protected function validateSafeMerge(array $target, array $source, string $targetKey): void
    {
        $conflicts = $this->findConflicts($target, $source);

        if (! empty($conflicts)) {
            throw new RuntimeException(
                "Config merge conflict: The following keys in '{$targetKey}' would be overwritten: " .
                implode(', ', $conflicts) . '. ' .
                'Disable safe mode with disableConfigSafeMode() if this is intentional.'
            );
        }
    }

    /**
     * Find conflicting keys between target and source
     *
     * @param array $target Target array
     * @param array $source Source array
     * @param string $prefix Key prefix for nested paths
     * @return array<string> Array of conflicting key paths
     */
    protected function findConflicts(array $target, array $source, string $prefix = ''): array
    {
        $conflicts = [];

        foreach ($source as $key => $value) {
            $fullKey = $prefix === '' ? $key : $prefix . '.' . $key;

            if (! isset($target[$key])) {
                // No conflict - key doesn't exist in target
                continue;
            }

            if (is_array($value) && is_array($target[$key])) {
                // Both are arrays - check recursively
                $nestedConflicts = $this->findConflicts($target[$key], $value, $fullKey);
                $conflicts = array_merge($conflicts, $nestedConflicts);
            } else {
                // Scalar or type mismatch - this is a conflict
                $conflicts[] = $fullKey;
            }
        }

        return $conflicts;
    }

    /**
     * Disable config safe mode
     *
     * Allows overwriting existing config values without validation.
     */
    public function disableConfigSafeMode(): static
    {
        $this->configSafeMode = false;

        return $this;
    }

    /**
     * Enable config safe mode
     *
     * Prevents overwriting existing config values (default behavior).
     */
    public function enableConfigSafeMode(): static
    {
        $this->configSafeMode = true;

        return $this;
    }

    /**
     * Check if config safe mode is enabled
     */
    public function isConfigSafeMode(): bool
    {
        return $this->configSafeMode;
    }

    /**
     * Get all queued config merges
     *
     * @return array<string, array{source: string, target: string, deep: bool}>
     */
    public function getQueuedConfigMerges(): array
    {
        return $this->configMerges;
    }

    /**
     * Clear all queued config merges
     */
    public function clearConfigMerges(): static
    {
        $this->configMerges = [];

        return $this;
    }

    /**
     * Validate a config key path
     *
     * @param string $key Key to validate
     *
     * @throws RuntimeException If key is invalid
     */
    protected function validateConfigKey(string $key): void
    {
        if (in_array(trim($key), ['', '0'], true)) {
            throw new RuntimeException('Config key cannot be empty');
        }

        // Check for invalid characters
        if (preg_match('/[^a-zA-Z0-9._-]/', $key)) {
            throw new RuntimeException(
                "Invalid config key '{$key}'. " .
                'Only alphanumeric characters, dots (.), dashes (-), and underscores (_) are allowed'
            );
        }
    }

    /**
     * Get config namespace (abstract - must be implemented by Package class)
     */
    abstract protected function getConfigNamespace(): string;
}
