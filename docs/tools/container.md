# Container bindings

Declaring what a package puts into the container, beside the rest of its surface.

## The problem this replaces

Every package that exposes a manager ends up writing the same lines by hand:

```php
public function packageRegistered(): void
{
    $this->app->singleton(FluxManager::class);
    $this->app->alias(FluxManager::class, 'flux');

    AliasLoader::getInstance()->alias('Flux', Facades\Flux::class);
}
```

Three separate mechanics for one intent — *this package exposes a facade* — and,
because they are statements rather than data, nothing else can see what the
package claimed.

## Declaring it instead

```php
$package
    ->name('laranail/flux-uikit')
    ->hasConfigFile('flux-uikit')
    ->hasViews()
    ->hasFacade('flux', FluxManager::class, Facades\Flux::class);
```

`hasFacade()` expands to a singleton, a container alias so the facade's
`getFacadeAccessor()` resolves, and — when a facade class is given — a global
class alias so `\Flux` works unqualified. The alias name defaults to the facade's short class name **with a trailing
`Facade` stripped** — `FluxFacade` implies the alias `Flux`, which is why the
class is named that way in the first place. Pass `alias:` when the class name
does not imply the alias.

## The full surface

| Method | Registers |
|---|---|
| `hasBinding($abstract, $concrete = null)` | `bind()` — resolved fresh each time |
| `hasSingleton($abstract, $concrete = null)` | `singleton()` |
| `hasScoped($abstract, $concrete = null)` | `scoped()` — once per request or job |
| `hasInstance($abstract, $instance)` | `instance()` |
| `hasContainerAlias($abstract, $alias)` | `alias()` — the short accessor a facade resolves |
| `hasClassAlias($alias, $class)` | an `AliasLoader` entry |
| `hasFacade($accessor, $concrete, $facade = null, $alias = null)` | all of the above, for the common shape |
| `registerUsing(Closure)` | an escape hatch, called with `($app, $package)` |

`$concrete` may be a class name or a closure. A closure can read config, because
bindings apply **after** the package's config has merged.

## When they run

During `register()`, after `registerPackageConfigs()` and before
`packageRegistered()`. So a factory closure can read the package's own config,
and a consumer's `packageRegistered()` hook sees a fully bound container.

## A class alias is refused, not taken

Laravel's `AliasLoader` is a flat global map, and `alias()` replaces whatever is
there. A package registering `Flux` can shadow the application's own alias, or
another package's, and the damage surfaces far away as the wrong class resolving
— the same silent-replacement failure the naming convention exists to prevent.

So an alias already bound to a **different** class is refused and recorded:

```php
$provider->refusedClassAliases;   // ['Flux' => App\Support\Flux::class]
```

An application that wants the package's alias can simply not define its own; one
declared in `config/app.php` wins because it was there first.

Container bindings are *not* guarded this way. Rebinding a class name is ordinary
Laravel and often deliberate, and a class name cannot collide the way a bare
string key can.

## What the registry reports

`PackageRegistry::describe()` includes `bindings` (the short accessors) and
`classAliases`, and `collisions()` covers both — so two packages claiming one
accessor is now a question you can answer.

Class aliases are read from the **live** loader, so one a package declared but
did not get is not reported as held. Same reasoning as publish tags: what the
framework ended up holding is the only thing that matters for a flat map.

## Why there is no `$package->app`

`Package` is a declarative value object, built before anything is registered.
Handing it a container would invite imperative work at configure time — resolving
services before their providers have registered, reading config before it has
merged — which is exactly the ordering `PackageServiceProvider` exists to keep
straight.

`registerUsing()` covers the same ground at the right moment:

```php
->registerUsing(function ($app, $package) {
    $app->singleton(Client::class, fn () => new Client(
        config($package->getDottedNamespace().'.token')
    ));
});
```

---

[← Docs index](../../README.md#documentation)
