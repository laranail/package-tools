<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider;

use Livewire\Livewire;

trait ProcessLivewireComponents
{
    /**
     * Register the package's Livewire components, prefixing each with the
     * package namespace (e.g. vendor::component-name).
     */
    protected function bootPackageLivewireComponents(): self
    {
        if (empty($this->package->livewireComponents)) {
            return $this;
        }

        if (! class_exists(Livewire::class)) {
            return $this;
        }

        $viewNamespace = $this->package->viewNamespace();

        foreach ($this->package->livewireComponents as $name => $class) {
            $componentName = str_contains((string) $name, '::')
                ? $name
                : "{$viewNamespace}::{$name}";

            Livewire::component($componentName, $class);
        }

        return $this;
    }
}
