<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

/**
 * HasBatchResourceLoading - Batch resource loading
 *
 * Enables loading multiple package resources in one call
 */
trait HasBatchResourceLoading
{
    /**
     * Load all standard package resources
     *
     * @param array<string> $resources Resources to load (empty = all)
     */
    public function loadAllResources(array $resources = []): static
    {
        $defaultResources = [
            'configs' => fn () => $this->autoLoadConfigs(),
            'views' => fn () => $this->autoLoadViews(),
            'translations' => fn () => $this->autoLoadTranslations(),
            'migrations' => fn () => $this->autoLoadMigrations(),
            'routes' => fn () => $this->autoLoadRoutes(),
            'commands' => fn () => $this->autoLoadCommands(),
        ];

        $toLoad = $resources === [] ? $defaultResources : array_intersect_key($defaultResources, array_flip($resources));

        foreach ($toLoad as $loader) {
            $loader();
        }

        return $this;
    }

    /**
     * Auto-load all config files from config directory
     */
    protected function autoLoadConfigs(): static
    {
        $configPath = $this->packageBasePath('config');

        if (is_dir($configPath)) {
            $files = glob($configPath . '/*.php') ?: [];

            foreach ($files as $file) {
                $fileName = basename($file, '.php');
                $this->hasConfigFile($fileName);
            }
        }

        return $this;
    }

    /**
     * Auto-load views if directory exists
     */
    protected function autoLoadViews(): static
    {
        $viewsPath = $this->packageBasePath('resources/views');

        if (is_dir($viewsPath)) {
            $this->hasViews();
        }

        return $this;
    }

    /**
     * Auto-load translations if directory exists
     */
    protected function autoLoadTranslations(): static
    {
        $langPath = $this->packageBasePath('resources/lang');

        if (is_dir($langPath)) {
            $this->hasTranslations();
        }

        return $this;
    }

    /**
     * Auto-load migrations if directory exists
     */
    protected function autoLoadMigrations(): static
    {
        $migrationsPath = $this->packageBasePath('database/migrations');

        if (is_dir($migrationsPath)) {
            $this->hasMigrations();
        }

        return $this;
    }

    /**
     * Auto-load routes if files exist
     */
    protected function autoLoadRoutes(): static
    {
        $routesPath = $this->packageBasePath('routes');

        if (is_dir($routesPath)) {
            if (file_exists($routesPath . '/web.php')) {
                $this->hasRoute('web');
            }
            if (file_exists($routesPath . '/api.php')) {
                $this->hasRoute('api');
            }
        }

        return $this;
    }

    /**
     * Auto-load commands if directory exists
     */
    protected function autoLoadCommands(): static
    {
        // Commands are typically registered in service provider
        // This is a placeholder for auto-discovery
        return $this;
    }

    /**
     * Get package base path
     *
     * @param string $path Optional path to append
     */
    abstract protected function packageBasePath(string $path = ''): string;

    abstract public function hasConfigFile($configFileName = null): static;

    abstract public function hasViews(?string $namespace = null): static;

    abstract public function hasTranslations(): static;

    abstract public function hasRoute(string $routeFileName): static;
}
