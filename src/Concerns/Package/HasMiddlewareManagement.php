<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;

/**
 * Registers route and global middleware, with a boot method to wire them
 * into Laravel.
 */
trait HasMiddlewareManagement
{
    /** @var array<string, string> Route middleware registry */
    protected array $routeMiddleware = [];

    /** @var array<string> Global middleware registry */
    protected array $globalMiddleware = [];

    /**
     * Register route middleware
     *
     * @param string $name Middleware alias
     * @param string $class Middleware class
     */
    public function registerRouteMiddleware(string $name, string $class): static
    {
        $this->routeMiddleware[$name] = $class;

        return $this;
    }

    /**
     * Register global middleware
     *
     * @param string $class Middleware class
     */
    public function registerGlobalMiddleware(string $class): static
    {
        $this->globalMiddleware[] = $class;

        return $this;
    }

    /**
     * Register middleware with Laravel. Call from the provider's boot().
     *
     * @param Router $router Laravel router instance
     */
    public function bootPackageMiddleware(Router $router): void
    {
        foreach ($this->routeMiddleware as $name => $class) {
            $router->aliasMiddleware($name, $class);
        }

        foreach ($this->globalMiddleware as $class) {
            app(Kernel::class)->pushMiddleware($class);
        }
    }

    /**
     * Get all route middleware
     *
     * @return array<string, string>
     */
    public function getRouteMiddleware(): array
    {
        return $this->routeMiddleware;
    }

    /**
     * Get all global middleware
     *
     * @return array<string>
     */
    public function getGlobalMiddleware(): array
    {
        return $this->globalMiddleware;
    }
}
