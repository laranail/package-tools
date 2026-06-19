<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Services\Config;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Arr;
use Simtabi\Laranail\PackageTools\Contracts\ServiceInterface;

/**
 * Merges, sets, gets, and forgets configuration values.
 */
class ConfigService implements ServiceInterface
{
    /** @var array<string, string> */
    protected array $mergedConfigs = [];

    public function __construct(protected Application $app) {}

    /**
     * Merge configuration from a file into a key
     *
     * @param string $path Path to config file
     * @param string $key Config key to merge into
     */
    public function merge(string $path, string $key): void
    {
        if (! file_exists($path)) {
            return;
        }

        $config = require $path;

        if (! is_array($config)) {
            return;
        }

        $existing = $this->app['config']->get($key, []);
        $merged = array_merge($config, $existing);

        $this->app['config']->set($key, $merged);
        $this->mergedConfigs[$key] = $path;
    }

    /**
     * Merge configuration into a global Laravel config key
     *
     * Used for configs that Laravel expects at root level:
     * - services.php
     * - mail.php
     * - queue.php
     * - broadcasting.php
     *
     * @param string $path Path to config file
     * @param string $globalKey Global config key (e.g., 'services', 'mail')
     */
    public function mergeGlobal(string $path, string $globalKey): void
    {
        if (! file_exists($path)) {
            return;
        }

        $config = require $path;

        if (! is_array($config)) {
            return;
        }

        $existing = $this->app['config']->get($globalKey, []);
        $merged = array_merge_recursive($existing, $config);

        $this->app['config']->set($globalKey, $merged);
    }

    /**
     * Set a configuration value
     *
     * @param string $key Configuration key (dot notation supported)
     * @param mixed $value Value to set
     */
    public function set(string $key, mixed $value): void
    {
        $this->app['config']->set($key, $value);
    }

    /**
     * Get a configuration value
     *
     * @param string $key Configuration key (dot notation supported)
     * @param mixed $default Default value if key doesn't exist
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->app['config']->get($key, $default);
    }

    /**
     * Forget/remove a configuration value
     *
     * @param string $key Configuration key to remove
     */
    public function forget(string $key): void
    {
        $items = $this->app['config']->all();
        Arr::forget($items, $key);

        foreach ($items as $itemKey => $itemValue) {
            $this->app['config']->set($itemKey, $itemValue);
        }
    }

    /**
     * Check if a configuration key exists
     *
     * @param string $key Configuration key
     */
    public function has(string $key): bool
    {
        return $this->app['config']->has($key);
    }

    /**
     * Get all merged configuration paths
     *
     * @return array<string, string> Array of key => path
     */
    public function getMergedConfigs(): array
    {
        return $this->mergedConfigs;
    }

    /**
     * {@inheritDoc}
     */
    public function isReady(): bool
    {
        return isset($this->app['config']);
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'config';
    }
}
