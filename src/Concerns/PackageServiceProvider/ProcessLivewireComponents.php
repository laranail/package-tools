<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\PackageServiceProvider;

use Livewire\Livewire;

trait ProcessLivewireComponents
{
    /**
     * Boot Livewire components
     *
     * Registers all Livewire components defined in the package configuration.
     * Components are registered with their namespace prefix (e.g., 'vendor::component-name')
     */
    protected function bootPackageLivewireComponents(): self
    {
        if (empty($this->package->livewireComponents)) {
            return $this;
        }

        // Only register if Livewire is available
        if (! class_exists(Livewire::class)) {
            return $this;
        }

        $viewNamespace = $this->package->viewNamespace();

        foreach ($this->package->livewireComponents as $name => $class) {
            // Auto-prefix with package namespace if not already prefixed
            $componentName = str_contains((string) $name, '::')
                ? $name
                : "{$viewNamespace}::{$name}";

            Livewire::component($componentName, $class);
        }

        return $this;
    }
}
