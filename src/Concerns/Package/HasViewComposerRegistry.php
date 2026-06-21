<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Simtabi\Laranail\Package\Tools\Services\View\ViewComposerRegistry;

/**
 * Registers view composers and creators, with dependency injection support.
 */
trait HasViewComposerRegistry
{
    protected ?ViewComposerRegistry $viewComposerRegistryService = null;

    /**
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
     * Register a composer for all of the package's views.
     *
     * @param string|callable $composer Composer class or callback
     */
    public function registerGlobalViewComposer(string|callable $composer): static
    {
        $viewNamespace = $this->shortName() . '::*';

        return $this->registerViewComposer($viewNamespace, $composer);
    }

    /**
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

    protected function getViewComposerRegistryService(): ViewComposerRegistry
    {
        if (! $this->viewComposerRegistryService) {
            $this->viewComposerRegistryService = app(ViewComposerRegistry::class);
        }

        return $this->viewComposerRegistryService;
    }

    abstract protected function shortName(): string;
}
