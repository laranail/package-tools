<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

use Livewire\Livewire;
use Simtabi\Laranail\PackageTools\Services\Component\ComponentRegistry;
use Throwable;

/**
 * HasSafeComponentRegistration - Safe component registration with error handling
 *
 * Enables component registration with try-catch and validation
 */
trait HasSafeComponentRegistration
{
    protected ?ComponentRegistry $safeComponentRegistry = null;

    protected array $componentErrors = [];

    /**
     * Safely register Blade component with error handling
     *
     * @param string $name Component name/alias
     * @param string $class Component class
     */
    public function safelyRegisterComponent(string $name, string $class): static
    {
        try {
            if (! class_exists($class)) {
                $this->componentErrors[] = "Component class not found: {$class}";

                return $this;
            }

            $registry = $this->getSafeComponentRegistry();
            $registry->register('blade', $class, $name);

        } catch (Throwable $e) {
            $this->componentErrors[] = "Failed to register component {$name}: " . $e->getMessage();
        }

        return $this;
    }

    /**
     * Safely register multiple components
     *
     * @param array<string, string> $components [name => class]
     */
    public function safelyRegisterComponents(array $components): static
    {
        foreach ($components as $name => $class) {
            $this->safelyRegisterComponent($name, $class);
        }

        return $this;
    }

    /**
     * Safely register Livewire component
     *
     * @param string $name Component alias
     * @param string $class Component class
     */
    public function safelyRegisterLivewireComponent(string $name, string $class): static
    {
        try {
            if (! class_exists($class)) {
                $this->componentErrors[] = "Livewire component class not found: {$class}";

                return $this;
            }

            if (! class_exists(Livewire::class)) {
                $this->componentErrors[] = 'Livewire not installed';

                return $this;
            }

            $registry = $this->getSafeComponentRegistry();
            $registry->register('livewire', $class, $name);

        } catch (Throwable $e) {
            $this->componentErrors[] = "Failed to register Livewire component {$name}: " . $e->getMessage();
        }

        return $this;
    }

    /**
     * Register components with validation
     *
     * @param array<string, string> $components [name => class]
     * @param string $type Component type ('blade', 'livewire', 'vue')
     */
    public function registerValidatedComponents(array $components, string $type = 'blade'): static
    {
        foreach ($components as $name => $class) {
            if ($this->validateComponent($name, $class, $type)) {
                $registry = $this->getSafeComponentRegistry();
                $registry->register($type, $class, $name);
            }
        }

        return $this;
    }

    /**
     * Validate component before registration
     *
     * @param string $name Component name
     * @param string $class Component class
     * @param string $type Component type
     */
    protected function validateComponent(string $name, string $class, string $type): bool
    {
        // Check class exists
        if (! class_exists($class)) {
            $this->componentErrors[] = "Component class not found: {$class}";

            return false;
        }

        // Check name is valid
        if ($name === '' || $name === '0' || ! preg_match('/^[a-z0-9\-_:]+$/i', $name)) {
            $this->componentErrors[] = "Invalid component name: {$name}";

            return false;
        }

        // Type-specific validation
        if ($type === 'livewire' && ! class_exists(Livewire::class)) {
            $this->componentErrors[] = "Livewire not installed, cannot register: {$name}";

            return false;
        }

        return true;
    }

    /**
     * Get component registration errors
     *
     * @return array<string>
     */
    public function getComponentErrors(): array
    {
        return $this->componentErrors;
    }

    /**
     * Check if any component errors occurred
     */
    public function hasComponentErrors(): bool
    {
        return ! empty($this->componentErrors);
    }

    /**
     * Get or create safe component registry instance
     */
    protected function getSafeComponentRegistry(): ComponentRegistry
    {
        if (! $this->safeComponentRegistry) {
            $this->safeComponentRegistry = app(ComponentRegistry::class);
        }

        return $this->safeComponentRegistry;
    }
}
