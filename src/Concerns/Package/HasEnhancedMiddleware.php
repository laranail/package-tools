<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

use Simtabi\Laranail\PackageTools\Services\Event\MiddlewareRegistry;

/**
 * HasEnhancedMiddleware - Enhanced middleware management
 *
 * Enables advanced middleware registration and grouping
 */
trait HasEnhancedMiddleware
{
    protected ?MiddlewareRegistry $packageMiddlewareRegistry = null;

    /**
     * Register middleware alias
     *
     * @param string $alias Middleware alias
     * @param string $class Middleware class
     */
    public function registerMiddlewareAlias(string $alias, string $class): static
    {
        $registry = $this->getPackageMiddlewareRegistry();
        $registry->register('alias', $alias, $class);

        return $this;
    }

    /**
     * Register multiple middleware aliases
     *
     * @param array<string, string> $aliases [alias => class]
     */
    public function registerMiddlewareAliases(array $aliases): static
    {
        foreach ($aliases as $alias => $class) {
            $this->registerMiddlewareAlias($alias, $class);
        }

        return $this;
    }

    /**
     * Register middleware group
     *
     * @param string $group Group name
     * @param array<string> $middleware Middleware classes
     */
    public function registerMiddlewareGroup(string $group, array $middleware): static
    {
        $registry = $this->getPackageMiddlewareRegistry();
        $registry->register('group', $group, $middleware);

        return $this;
    }

    /**
     * Register multiple middleware groups
     *
     * @param array<string, array> $groups [group => middleware[]]
     */
    public function registerMiddlewareGroups(array $groups): static
    {
        foreach ($groups as $group => $middleware) {
            $this->registerMiddlewareGroup($group, $middleware);
        }

        return $this;
    }

    /**
     * Add middleware to existing group
     *
     * @param string $group Group name
     * @param string $middleware Middleware class
     */
    public function addToMiddlewareGroup(string $group, string $middleware): static
    {
        $registry = $this->getPackageMiddlewareRegistry();
        $current = $registry->get('group', $group, []);
        $current[] = $middleware;

        $registry->register('group', $group, $current);

        return $this;
    }

    /**
     * Register package middleware with prefix
     *
     * @param array<string, string> $middleware [alias => class]
     * @param string|null $prefix Alias prefix (defaults to package name)
     */
    public function registerPrefixedMiddleware(array $middleware, ?string $prefix = null): static
    {
        $prefix ??= $this->shortName();

        foreach ($middleware as $alias => $class) {
            $prefixedAlias = "{$prefix}.{$alias}";
            $this->registerMiddlewareAlias($prefixedAlias, $class);
        }

        return $this;
    }

    /**
     * Get or create package middleware registry instance
     */
    protected function getPackageMiddlewareRegistry(): MiddlewareRegistry
    {
        if (! $this->packageMiddlewareRegistry) {
            $this->packageMiddlewareRegistry = app(MiddlewareRegistry::class);
        }

        return $this->packageMiddlewareRegistry;
    }

    /**
     * Get package short name
     */
    abstract protected function shortName(): string;
}
