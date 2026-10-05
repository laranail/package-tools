# Naming assertions

`AssertsRegisteredNames` asserts, against the live registries of a booted application, that every name a package registers carries its vendor and slug, apart from an explicit list of deprecated aliases.

## Why

Route names, rate limiters, Artisan commands, middleware aliases, view and translation namespaces,
Livewire components, gate abilities, Blade components and container aliases each live in a flat map
keyed by the name. A second package claiming a key does not collide loudly. It silently replaces the
first. Grepping a provider proves how a registration was written, not what the framework ended up
holding, so these assertions read the registries themselves.

## Usage

```php
use Simtabi\Laranail\Package\Tools\Testing\NamingScope;
use Simtabi\Laranail\Package\Tools\Testing\AssertsRegisteredNames;

uses(AssertsRegisteredNames::class);

it('scopes every public name', function (): void {
    $scope = NamingScope::for('laranail/installer-web', 'Simtabi\\Laranail\\Installer\\Web\\');

    $this->assertRouteNamesScoped($scope, atLeast: 10);
    $this->assertRateLimitersScoped($scope, deprecated: ['installer', 'installer-gate']);
    $this->assertMiddlewareAliasesScoped($scope, atLeast: 6);
    $this->assertLivewireComponentsScoped($scope, deprecated: ['installer-wizard-step']);
    $this->assertViewNamespacesScoped($scope);
});
```

Every assertion takes the scope, a `deprecated` allow-list and an `atLeast` floor, and returns the
scoped names it found. `assertRegisteredNamesScoped(NameRegistry $registry, …)` is the general form.
`NamingAssertions` holds the same checks as static methods for use without the trait.

## What fails

- **An owned name without the scoped shape** that is not listed as deprecated.
- **A deprecated entry that is not registered.** An allow-list entry is a ceiling, and a stale one
  would cover whatever claims the name next.
- **Fewer scoped names than `atLeast`.** A scope that matches nothing passes everything else
  trivially. Pass the count you expect wherever it is known.

## The registries

| Method | Registry read | Default shape for `laranail/atlas` |
|---|---|---|
| `assertRouteNamesScoped` | router route names | `laranail-atlas.*` |
| `assertRateLimitersScoped` | `RateLimiter::$limiters` (reflection) | `laranail-atlas.*` |
| `assertCommandNamesScoped` | `Artisan::all()`, aliases included | `laranail::atlas.*` |
| `assertMiddlewareAliasesScoped` | `Router::getMiddleware()` | `laranail-atlas`, `laranail-atlas.*` |
| `assertViewNamespacesScoped` | view finder hints | `laranail/atlas`, `laranail-atlas` |
| `assertTranslationNamespacesScoped` | translation loader namespaces | `laranail/atlas`, `laranail-atlas` |
| `assertLivewireComponentsScoped` | Livewire 4 finder, or Livewire 3 registry | `laranail-atlas.*` |
| `assertGateAbilitiesScoped` | `Gate::abilities()` | `laranail-atlas.*` |
| `assertBladeComponentsScoped` | class aliases, class and anonymous prefixes | `laranail-atlas::*` |
| `assertContainerAliasesScoped` | container aliases and bindings | `laranail-atlas`, `laranail/atlas`, `laranail.atlas`, any class name |

A name matches a prefix when it equals it or continues it with `.`, `:` or `/`. A hyphen does not
count as a continuation, because `laranail-atlas-pro.*` belongs to `laranail/atlas-pro`.

A registry read by reflection throws if the property is gone, rather than reading as empty. So does
the Livewire assertion in an application without Livewire.

## Which names are the package's

A name is judged only if it belongs to the package. It does when any of these holds:

- what it maps to is a class in the owner namespace (a controller, middleware, command, component);
- it is a closure whose scope class is in the owner namespace, or that was defined under the base
  path;
- it is a path under the base path (a view or translation directory);
- the name is the bare slug, or continues it (`atlas`, `atlas.page`). This catches a bare name
  registered through a closure that carries no evidence of its owner.

The base path defaults to the parent of the namespace's PSR-4 directory, read from Composer's
autoloader: the package root, so `resources/`, `routes/` and `config/` count as the package's. Pass
`basePath:` when the package is autoloaded another way. An application's own `dashboard` route is
not the package's, and is never reported.

`vendor/` and `tests/` directly under the base path are never the package's, whatever base path is
passed. In the package's own suite the root holds the installed framework and the test harness too,
and without the exclusion every framework binding (`events`, `log`, `router`) and every name the
TestCase defines (an `api` rate limiter) would be reported as the package's offence. Register a
name the assertion should judge from `src/`, not from the TestCase.

## Sanctioned variants

Where the family has accepted a different vendor-scoped shape, pass it per registry:

```php
NamingScope::for(
    package: 'laranail/db-console',
    ownerNamespace: 'Simtabi\\Laranail\\DBConsole\\',
    prefixes: [NameRegistry::Route->value => ['laranail-db-console', 'laranail.db-console']],
);
```

## Deprecated route names

```php
$this->assertDeprecatedRouteNamesResolve(
    ['installer-web.show' => 'laranail-installer-web.show'],
    parameters: ['installer-web.show' => ['step' => 'welcome']],
);
```

Each bare name must not be a route of its own and must generate the same URL as its scoped route.
Deprecation notices raised along the way are swallowed, so assert them separately. See
[Route name aliases](route-name-aliases.md).

## Checking it has teeth

This package's own suite runs every assertion against a stand-in package and requires it to pass
when the package is scoped, fail on a bare owned name, allow a listed alias, fail on a stale one, and
fail below the floor. A package adopting the trait should still break one name and watch its own
test go red before trusting it.

---

[← Docs index](../../README.md#documentation)
