<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

use Closure;

trait HasLivewireComponents
{
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
     * Register multiple Livewire components
     *
     * Accepts either:
     * - Array of ['name' => ClassName::class]
     * - Closure that returns array of ['name' => ClassName::class]
     *
     *
     * @example
     * $package->hasLivewireComponents([
     *     'icon-browser' => IconBrowser::class,
     *     'icon-picker' => IconPicker::class,
     * ]);
     * @example
     * $package->hasLivewireComponents(fn() => [
     *     'icon-browser' => IconBrowser::class,
     * ]);
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
