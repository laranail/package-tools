<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

use Simtabi\Laranail\PackageTools\Services\Config\ConfigFileResolver;

/**
 * HasNestedConfigFiles - Nested configuration file support
 *
 * Enables loading config files from nested directories
 */
trait HasNestedConfigFiles
{
    protected ?ConfigFileResolver $configFileResolver = null;

    /**
     * Load configuration from nested directory
     *
     * @param string $fileName Config file name (without .php)
     * @param string $folder Nested folder path (e.g., 'admin', 'api/v1')
     * @param string|null $key Config key (defaults to fileName)
     */
    public function hasNestedConfig(string $fileName, string $folder = '', ?string $key = null): static
    {
        $resolver = $this->getConfigFileResolver();
        $configPath = $resolver->resolve($fileName, ['folder' => $folder]);

        if (file_exists($configPath)) {
            $this->hasConfigFile($fileName);
        }

        return $this;
    }

    /**
     * Load multiple nested configs
     *
     * @param array<string> $files Array of file names
     * @param string $folder Nested folder path
     */
    public function hasNestedConfigs(array $files, string $folder = ''): static
    {
        foreach ($files as $fileName) {
            $this->hasNestedConfig($fileName, $folder);
        }

        return $this;
    }

    /**
     * Load all configs from a nested directory
     *
     * @param string $folder Folder path relative to config directory
     */
    public function hasConfigDirectory(string $folder): static
    {
        $configPath = $this->packageBasePath('config/' . trim($folder, '/'));

        if (is_dir($configPath)) {
            $files = glob($configPath . '/*.php') ?: [];

            foreach ($files as $file) {
                $fileName = basename($file, '.php');
                $this->hasNestedConfig($fileName, $folder);
            }
        }

        return $this;
    }

    /**
     * Get or create config file resolver instance
     */
    protected function getConfigFileResolver(): ConfigFileResolver
    {
        if (! $this->configFileResolver) {
            $this->configFileResolver = new ConfigFileResolver($this->packageBasePath());
        }

        return $this->configFileResolver;
    }

    /**
     * Get package base path
     *
     * @param string $path Optional path to append
     */
    abstract protected function packageBasePath(string $path = ''): string;

    abstract public function hasConfigFile($configFileName = null): static;
}
