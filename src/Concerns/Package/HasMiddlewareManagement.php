<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;

/**
 * HasMiddlewareManagement - Enhanced middleware and event registration
 *
 * Provides organized middleware and event management with:
 * - Route middleware registration
 * - Global middleware registration
 * - Event listener registration
 * - Event subscriber registration
 * - Boot methods for automatic registration
 */
trait HasMiddlewareManagement
{
    /** @var array<string, string> Route middleware registry */
    protected array $routeMiddleware = [];

    /** @var array<string> Global middleware registry */
    protected array $globalMiddleware = [];

    /** @var array<string, array<string>> Event listeners registry */
    protected array $eventListeners = [];

    /** @var array<string> Event subscribers registry */
    protected array $eventSubscribers = [];

    /**
     * Register route middleware
     *
     * @param string $name Middleware alias
     * @param string $class Middleware class
     *
     * @example
     * ```php
     * $package->registerRouteMiddleware('auth.blog', BlogAuthMiddleware::class);
     * ```
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
     * Register event listener
     *
     * @param string $event Event class or name
     * @param string $listener Listener class
     */
    public function registerEventListener(string $event, string $listener): static
    {
        if (! isset($this->eventListeners[$event])) {
            $this->eventListeners[$event] = [];
        }

        $this->eventListeners[$event][] = $listener;

        return $this;
    }

    /**
     * Register event subscriber
     *
     * @param string $subscriber Subscriber class
     */
    public function registerEventSubscriber(string $subscriber): static
    {
        $this->eventSubscribers[] = $subscriber;

        return $this;
    }

    /**
     * Boot package middleware (registers with Laravel)
     *
     * This method should be called from the service provider's boot() method.
     *
     * @param Router $router Laravel router instance
     */
    public function bootPackageMiddleware(Router $router): void
    {
        // Register route middleware
        foreach ($this->routeMiddleware as $name => $class) {
            $router->aliasMiddleware($name, $class);
        }

        // Register global middleware
        foreach ($this->globalMiddleware as $class) {
            app(Kernel::class)->pushMiddleware($class);
        }
    }

    /**
     * Boot package event listeners (registers with Laravel)
     *
     * This method should be called from the service provider's boot() method.
     */
    public function bootPackageEventListeners(): void
    {
        foreach ($this->eventListeners as $event => $listeners) {
            foreach ($listeners as $listener) {
                Event::listen($event, $listener);
            }
        }
    }

    /**
     * Boot package event subscribers (registers with Laravel)
     *
     * This method should be called from the service provider's boot() method.
     */
    public function bootPackageEventSubscribers(): void
    {
        foreach ($this->eventSubscribers as $subscriber) {
            Event::subscribe($subscriber);
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

    /**
     * Get all event listeners
     *
     * @return array<string, array<string>>
     */
    public function getEventListeners(): array
    {
        return $this->eventListeners;
    }

    /**
     * Get all event subscribers
     *
     * @return array<string>
     */
    public function getEventSubscribers(): array
    {
        return $this->eventSubscribers;
    }
}
