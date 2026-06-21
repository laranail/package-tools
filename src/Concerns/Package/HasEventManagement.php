<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Registers event listeners and subscribers for a package on a deferred-array
 * model: registrations are stored now and wired into Laravel at boot.
 */
trait HasEventManagement
{
    /** @var array<string, array<string>> Event listeners registry */
    protected array $eventListeners = [];

    /** @var array<string> Event subscribers registry */
    protected array $eventSubscribers = [];

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
     * Register multiple event listeners.
     *
     * Accepts both `[event => listener]` and `[event => [l1, l2]]` shapes.
     *
     * @param array<string, string|array<string>> $listeners [event => listener(s)]
     */
    public function registerEventListeners(array $listeners): static
    {
        foreach ($listeners as $event => $listener) {
            foreach ((array) $listener as $single) {
                $this->registerEventListener($event, $single);
            }
        }

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
     * Register multiple event subscribers
     *
     * @param array<string> $subscribers Subscriber classes
     */
    public function registerEventSubscribers(array $subscribers): static
    {
        foreach ($subscribers as $subscriber) {
            $this->registerEventSubscriber($subscriber);
        }

        return $this;
    }

    /**
     * Auto-discover and register event listeners from a directory.
     *
     * Each `*.php` file is reflected; the event is inferred from the first
     * parameter of the listener's `handle()` (or `__invoke()`) method. Only
     * a non-builtin, named type qualifies — anything else is skipped.
     *
     * @param string $directory Directory path (relative to package root)
     * @param string $namespace Base namespace for listeners
     */
    public function discoverEventListeners(string $directory = 'src/Listeners', string $namespace = ''): static
    {
        $full = $this->packageBasePath($directory);

        if (! File::isDirectory($full)) {
            return $this;
        }

        $files = glob($full . '/*.php') ?: [];

        foreach ($files as $file) {
            $shortName = basename($file, '.php');
            $class = $namespace !== ''
                ? rtrim($namespace, '\\') . '\\' . $shortName
                : $shortName;

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            if ($reflection->isAbstract()) {
                continue;
            }
            if ($reflection->isInterface()) {
                continue;
            }

            if ($reflection->hasMethod('handle')) {
                $method = $reflection->getMethod('handle');
            } elseif ($reflection->hasMethod('__invoke')) {
                $method = $reflection->getMethod('__invoke');
            } else {
                continue;
            }

            $params = $method->getParameters();

            if ($params === []) {
                continue;
            }

            $type = $params[0]->getType();
            if (! $type instanceof ReflectionNamedType) {
                continue;
            }
            if ($type->isBuiltin()) {
                continue;
            }

            $this->registerEventListener($type->getName(), $class);
        }

        return $this;
    }

    /**
     * Register event listeners with Laravel. Call from the provider's boot().
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
     * Register event subscribers with Laravel. Call from the provider's boot().
     */
    public function bootPackageEventSubscribers(): void
    {
        foreach ($this->eventSubscribers as $subscriber) {
            Event::subscribe($subscriber);
        }
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

    /**
     * Get package base path
     *
     * @param string $path Optional path to append
     */
    abstract protected function packageBasePath(string $path = ''): string;
}
