<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Closure;

trait HasLivewireComponents
{
    /** @var array<string, string> Map of component name => class */
    public array $livewireComponents = [];

    /**
     * Register a single Livewire component
     *
     * @param string $name Component name (e.g., 'icon-browser')
     * @param string $class Component class (e.g., IconBrowser::class)
     */
    public function hasLivewireComponent(string $name, string $class): static
    {
        $this->livewireComponents[$name] = $class;

        return $this;
    }

    /**
     * Register multiple Livewire components from an array of
     * ['name' => ClassName::class], or a closure returning one.
     *
     * @param array<string, string>|Closure $components
     */
    public function hasLivewireComponents(array|Closure $components): static
    {
        if ($components instanceof Closure) {
            $components = $components();
        }

        foreach ($components as $name => $class) {
            $this->hasLivewireComponent($name, $class);
        }

        return $this;
    }
}
