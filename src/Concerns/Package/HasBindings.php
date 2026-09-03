<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Closure;

/**
 * Container bindings, declared rather than written by hand.
 *
 * Every package that exposes a manager or a service ends up writing the same
 * three or four lines inside `packageRegistered()`. Declaring them here puts
 * them beside the rest of the package's surface, and - because they are data -
 * lets PackageRegistry report what a package bound, which is the one question
 * a flat container map cannot answer for you afterwards.
 *
 * Bindings are applied during REGISTER, after the package's config has merged
 * so a factory closure can read it, and before `packageRegistered()` so a
 * consumer's own hook sees a fully bound container.
 */
trait HasBindings
{
    /** @var array<string, array{concrete: Closure|string|null, lifetime: string}> */
    public array $bindings = [];

    /** @var array<string, mixed> */
    public array $instances = [];

    /** @var array<string, string> abstract => short accessor */
    public array $containerAliases = [];

    /** @var list<Closure> */
    public array $registerCallbacks = [];

    /**
     * Bind a class or interface, resolved fresh on every make().
     */
    public function hasBinding(string $abstract, Closure|string|null $concrete = null): static
    {
        $this->bindings[$abstract] = ['concrete' => $concrete, 'lifetime' => 'bind'];

        return $this;
    }

    /**
     * Bind a class or interface once per container.
     */
    public function hasSingleton(string $abstract, Closure|string|null $concrete = null): static
    {
        $this->bindings[$abstract] = ['concrete' => $concrete, 'lifetime' => 'singleton'];

        return $this;
    }

    /**
     * Bind once per request/job lifecycle rather than once per container.
     */
    public function hasScoped(string $abstract, Closure|string|null $concrete = null): static
    {
        $this->bindings[$abstract] = ['concrete' => $concrete, 'lifetime' => 'scoped'];

        return $this;
    }

    /**
     * Bind an already-constructed object.
     */
    public function hasInstance(string $abstract, mixed $instance): static
    {
        $this->instances[$abstract] = $instance;

        return $this;
    }

    /**
     * Give a binding a short accessor, the way a Facade resolves it.
     *
     * This is a CONTAINER alias, not a global class alias - see hasClassAlias().
     */
    public function hasContainerAlias(string $abstract, string $alias): static
    {
        $this->containerAliases[$abstract] = $alias;

        return $this;
    }

    /**
     * Escape hatch for wiring the fluent API cannot express.
     *
     * The closure receives the container and the package, and runs at the same
     * point as the declared bindings. Deliberately NOT a `$package->app`
     * property: Package is built before anything is registered, and handing it
     * a container would invite imperative work at configure time - which is
     * exactly the register/boot ordering this class exists to keep straight.
     *
     * @param Closure(\Illuminate\Contracts\Foundation\Application, static): void $callback
     */
    public function registerUsing(Closure $callback): static
    {
        $this->registerCallbacks[] = $callback;

        return $this;
    }
}
