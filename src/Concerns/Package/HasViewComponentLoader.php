<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

use Simtabi\Laranail\PackageTools\Services\View\ViewComponentLoader;

/**
 * HasViewComponentLoader - View component loading
 *
 * Enables loading view components from various sources
 */
trait HasViewComponentLoader
{
    protected ?ViewComponentLoader $viewComponentLoaderService = null;

    /**
     * Load view components from path
     *
     * @param string $path Path to components directory
     * @param string $namespace Component namespace
     */
    public function loadViewComponents(string $path, string $namespace): static
    {
        $loader = $this->getViewComponentLoaderService();

        $fullPath = $this->packageBasePath($path);
        $loader->loadFromPath($fullPath, $namespace);

        return $this;
    }

    /**
     * Load component namespace with classes
     *
     * @param string $namespace Component namespace
     * @param array<string, string> $components [name => class]
     */
    public function loadComponentNamespace(string $namespace, array $components): static
    {
        $loader = $this->getViewComponentLoaderService();
        $loader->loadNamespace($namespace, $components);

        return $this;
    }

    /**
     * Load anonymous components from path
     *
     * @param string $path Path to components directory
     * @param string $prefix Component prefix
     */
    public function loadAnonymousViewComponents(string $path, string $prefix): static
    {
        $loader = $this->getViewComponentLoaderService();

        $fullPath = $this->packageBasePath($path);
        $loader->loadAnonymous($fullPath, $prefix);

        return $this;
    }

    /**
     * Auto-load components from standard directory
     */
    public function autoLoadViewComponents(): static
    {
        $componentsPath = $this->packageBasePath('resources/views/components');

        if (is_dir($componentsPath)) {
            $this->loadAnonymousViewComponents('resources/views/components', $this->shortName());
        }

        return $this;
    }

    /**
     * Get or create view component loader service instance
     */
    protected function getViewComponentLoaderService(): ViewComponentLoader
    {
        if (! $this->viewComponentLoaderService) {
            $this->viewComponentLoaderService = app(ViewComponentLoader::class);
        }

        return $this->viewComponentLoaderService;
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
