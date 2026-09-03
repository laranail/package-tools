# Exposing a facade

One statement, three registrations:

```php
$package->hasFacade('hello', HelloManager::class, Facades\HelloFacade::class);
```

That binds `HelloManager` as a singleton, aliases it to `hello` so the facade's
`getFacadeAccessor()` resolves, and registers a global class alias so `\Hello`
works unqualified.

## The alias name

It defaults to the facade's short class name **with a trailing `Facade` stripped**
— `HelloFacade` implies `Hello`, which is why the class is named that way. State
it when the class name does not imply the alias:

```php
$package->hasFacade(
    accessor: 'hello',
    concrete: HelloManager::class,
    facade:   Facades\HelloFacade::class,
    alias:    'Howdy',
);
```

## It will refuse to overwrite

`AliasLoader` is a flat global map, so `alias()` replaces whatever is there. An
alias already bound to a different class is **refused**, not taken, and recorded:

```php
$provider->refusedClassAliases;   // ['Hello' => App\Support\Hello::class]
```

An application that wants its own `Hello` simply defines one; it was there first
and it wins.

## Do not also declare it in composer.json

An `extra.laravel.aliases` entry bypasses that guard entirely. Declare the alias
once, here.

## More

[Container bindings](../tools/container.md)

---

[← Docs index](../../README.md#documentation)
