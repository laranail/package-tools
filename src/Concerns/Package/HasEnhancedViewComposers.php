<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

use Illuminate\Support\Facades\View;
use Throwable;

/**
 * Registers view composers with automatic namespace prefixing, wildcard
 * patterns, and multiple composers per view.
 */
trait HasEnhancedViewComposers
{
    /** @var array<string, array<string>> View composer registry */
    protected array $viewComposerRegistry = [];

    /** @var bool Auto-prefix views with package namespace */
    protected bool $autoPrefixViewComposers = true;

    /**
     * Register a view composer
     *
     * @param string|array<string> $views View name(s)
     * @param string $composer Composer class
     * @param bool $autoPrefix Automatically prefix with view namespace
     *
     * @example
     * ```php
     * $package->registerViewComposer('dashboard', DashboardComposer::class);
     * // Registers: 'vendor::dashboard'
     * ```
     */
    public function registerViewComposer(
        string|array $views,
        string $composer,
        bool $autoPrefix = true
    ): static {
        $views = (array) $views;

        foreach ($views as $view) {
            $viewName = $autoPrefix && $this->autoPrefixViewComposers
                ? $this->prefixViewName($view)
                : $view;

            if (! isset($this->viewComposerRegistry[$viewName])) {
                $this->viewComposerRegistry[$viewName] = [];
            }

            $this->viewComposerRegistry[$viewName][] = $composer;
        }

        return $this;
    }

    /**
     * Register the queued composers with Laravel. Call from the service
     * provider's boot() method.
     */
    public function bootPackageViewComposers(): void
    {
        foreach ($this->viewComposerRegistry as $viewName => $composers) {
            foreach ($composers as $composer) {
                try {
                    View::composer($viewName, $composer);
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }
    }

    /**
     * Prefix view name with package namespace
     *
     * @param string $view View name
     * @return string Prefixed view name
     */
    protected function prefixViewName(string $view): string
    {
        $namespace = $this->getViewNamespace();

        if (str_starts_with($view, $namespace . '::')) {
            return $view;
        }

        return $namespace . '::' . $view;
    }

    /**
     * Get all registered view composers
     *
     * @return array<string, array<string>>
     */
    public function getViewComposerRegistry(): array
    {
        return $this->viewComposerRegistry;
    }

    /**
     * Disable auto-prefixing for view composers
     */
    public function disableViewComposerAutoPrefix(): static
    {
        $this->autoPrefixViewComposers = false;

        return $this;
    }

    /**
     * Get the package view namespace.
     */
    abstract protected function getViewNamespace(): string;
}
