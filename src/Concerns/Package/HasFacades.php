<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

/**
 * Facades, and the global class aliases that make them reachable unqualified.
 *
 * A package exposing a facade needs three separate registrations: a singleton,
 * a short container accessor for the facade to resolve, and - if the facade
 * should be reachable as a bare `\Flux` - an entry in Laravel's AliasLoader.
 * Writing them by hand means writing three mechanics; hasFacade() states the
 * intent once.
 *
 * The AliasLoader is another FLAT GLOBAL MAP, so a package registering an alias
 * silently replaces whatever the application or another package put there.
 * ProcessContainer refuses to overwrite an existing alias for that reason.
 */
trait HasFacades
{
    /** @var array<string, string> alias => fully-qualified class */
    public array $classAliases = [];

    /**
     * Expose a service through a facade.
     *
     * ```php
     * $package->hasFacade('flux', FluxManager::class, Facades\Flux::class);
     * ```
     *
     * Registers a singleton for `$concrete`, aliases it to `$accessor` so the
     * facade resolves, and - when `$facade` is given - registers a global class
     * alias so `\Flux` works unqualified.
     *
     * @param string $accessor what the facade's getFacadeAccessor() returns
     * @param string $concrete the class the facade proxies
     * @param string|null $facade the Facade subclass, if it should be globally aliased
     * @param string|null $alias the global alias; defaults to the facade's short name
     */
    public function hasFacade(string $accessor, string $concrete, ?string $facade = null, ?string $alias = null): static
    {
        $this->hasSingleton($concrete);
        $this->hasContainerAlias($concrete, $accessor);

        if ($facade !== null) {
            $this->hasClassAlias($alias ?? class_basename($facade), $facade);
        }

        return $this;
    }

    /**
     * Register a global class alias through Laravel's AliasLoader.
     *
     * Refused at register time if the alias is already taken - see the class
     * docblock.
     */
    public function hasClassAlias(string $alias, string $class): static
    {
        $this->classAliases[$alias] = $class;

        return $this;
    }
}
