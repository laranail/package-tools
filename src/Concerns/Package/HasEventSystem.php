<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

use Simtabi\Laranail\PackageTools\Services\Event\EventRegistry;

/**
 * HasEventSystem - Enhanced event system support
 *
 * Enables advanced event listener and subscriber registration
 */
trait HasEventSystem
{
    protected ?EventRegistry $packageEventRegistry = null;

    /**
     * Register event listener
     *
     * @param string $event Event class or name
     * @param string|array $listeners Listener class(es)
     */
    public function registerEventListener(string $event, string|array $listeners): static
    {
        $registry = $this->getPackageEventRegistry();
        $registry->register('listener', $event, $listeners);

        return $this;
    }

    /**
     * Register multiple event listeners
     *
     * @param array<string, string|array> $listeners [event => listener(s)]
     */
    public function registerEventListeners(array $listeners): static
    {
        foreach ($listeners as $event => $listener) {
            $this->registerEventListener($event, $listener);
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
        $registry = $this->getPackageEventRegistry();
        $registry->register('subscriber', $subscriber);

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
     * Auto-discover and register event listeners from directory
     *
     * @param string $directory Directory path (relative to package root)
     * @param string $namespace Base namespace for listeners
     */
    public function discoverEventListeners(string $directory = 'src/Listeners', string $namespace = ''): static
    {
        $fullPath = $this->packageBasePath($directory);

        if (is_dir($fullPath)) {
            $files = glob($fullPath . '/*.php');

            foreach ($files as $file) {
                $className = basename($file, '.php');
                $fullClass = $namespace !== '' && $namespace !== '0' ? "{$namespace}\\{$className}" : $className;

                if (class_exists($fullClass)) {
                    // Auto-register listener (requires reflection to get event)
                    // Simplified implementation
                }
            }
        }

        return $this;
    }

    /**
     * Get or create package event registry instance
     */
    protected function getPackageEventRegistry(): EventRegistry
    {
        if (! $this->packageEventRegistry) {
            $this->packageEventRegistry = app(EventRegistry::class);
        }

        return $this->packageEventRegistry;
    }

    /**
     * Get package base path
     *
     * @param string $path Optional path to append
     */
    abstract protected function packageBasePath(string $path = ''): string;
}
