<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

use Simtabi\Laranail\PackageTools\Services\View\ViewComposerRegistry;

/**
 * HasViewComposerRegistry - View composer management
 *
 * Enables registering view composers with advanced features
 */
trait HasViewComposerRegistry
{
    protected ?ViewComposerRegistry $viewComposerRegistryService = null;

    /**
     * Register view composer
     *
     * @param string|array $views View name(s)
     * @param string|callable $composer Composer class or callback
     */
    public function registerViewComposer(string|array $views, string|callable $composer): static
    {
        $registry = $this->getViewComposerRegistryService();
        $registry->register($views, $composer);

        return $this;
    }

    /**
     * Register multiple view composers
     *
     * @param array<string|array, string|callable> $composers [views => composer]
     */
    public function registerViewComposers(array $composers): static
    {
        foreach ($composers as $views => $composer) {
            $this->registerViewComposer($views, $composer);
        }

        return $this;
    }

    /**
     * Register view composer for all package views
     *
     * @param string|callable $composer Composer class or callback
     */
    public function registerGlobalViewComposer(string|callable $composer): static
    {
        $viewNamespace = $this->shortName() . '::*';

        return $this->registerViewComposer($viewNamespace, $composer);
    }

    /**
     * Register view creator
     *
     * @param string|array $views View name(s)
     * @param string|callable $creator Creator class or callback
     */
    public function registerViewCreator(string|array $views, string|callable $creator): static
    {
        $registry = $this->getViewComposerRegistryService();
        $registry->registerCreator($views, $creator);

        return $this;
    }

    /**
     * Register view composer with dependencies
     *
     * @param string|array $views View name(s)
     * @param string $composer Composer class
     * @param array $dependencies Dependencies to inject
     */
    public function registerViewComposerWithDependencies(string|array $views, string $composer, array $dependencies = []): static
    {
        $registry = $this->getViewComposerRegistryService();
        $registry->registerWithDependencies($views, $composer, $dependencies);

        return $this;
    }

    /**
     * Get or create view composer registry service instance
     */
    protected function getViewComposerRegistryService(): ViewComposerRegistry
    {
        if (! $this->viewComposerRegistryService) {
            $this->viewComposerRegistryService = app(ViewComposerRegistry::class);
        }

        return $this->viewComposerRegistryService;
    }

    /**
     * Get package short name
     */
    abstract protected function shortName(): string;
}
