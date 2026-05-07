<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

use Illuminate\Support\Facades\View;
use Throwable;

/**
 * HasEnhancedViewComposers - Enhanced view composer registration
 *
 * Provides advanced view composer management with:
 * - Automatic namespace prefixing
 * - Wildcard view patterns
 * - Multiple composers per view
 * - Boot methods with error handling
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
     * @example Single view
     * ```php
     * $package->registerViewComposer('dashboard', DashboardComposer::class);
     * // Registers: 'vendor::dashboard'
     * ```
     * @example Multiple views
     * ```php
     * $package->registerViewComposer(['index', 'show'], BlogComposer::class);
     * ```
     * @example Wildcard
     * ```php
     * $package->registerViewComposer('blog.*', BlogComposer::class);
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
     * Boot package view composers (registers with Laravel)
     *
     * This method should be called from the service provider's boot() method.
     */
    public function bootPackageViewComposers(): void
    {
        foreach ($this->viewComposerRegistry as $viewName => $composers) {
            foreach ($composers as $composer) {
                try {
                    View::composer($viewName, $composer);
                } catch (Throwable $e) {
                    // Log error but don't break application
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

        // Don't double-prefix
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
     * Get view namespace (abstract - must be implemented)
     */
    abstract protected function getViewNamespace(): string;
}
