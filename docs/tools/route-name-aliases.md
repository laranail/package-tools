# Route name aliases

`Simtabi\Laranail\Package\Tools\Support\Routing\BareRouteNameAliases` keeps a package's deprecated bare route names resolving to its vendor-scoped routes, and answers `has()` and `currentIs()` for them.

## Why

Route names live in one flat registry, so a bare `error-pages.preview` collides with a sibling
package's or the application's route of the same name, and the later registration silently wins. A
package therefore registers `laranail-error-pages.preview`. Hosts written against the old name keep
working through `URL::resolveMissingNamedRoutesUsing()`, which Laravel consults only for a name it
does not hold. An application's own route under the bare name is therefore never shadowed. The hook
is a runtime callback, so it survives `route:cache`.

Five packages hand-rolled this (error-pages, env-kit-webui, db-console-webui, installer-web,
authkit-preset), and two of the copies had defects: one passed a foreign resolver's answer through
unchecked (a `TypeError` under `strict_types`), and one wrote a log line per link per request. This
class is a superset of all five.

## Declaring it on the package

```php
$package->name('laranail/error-pages')
    ->hasDeprecatedRouteNames(
        prefixes: ['error-pages.' => 'laranail-error-pages.'],
    );
```

The provider installs it during boot. `$package->deprecatedRouteNames()` returns the installed
instance, or `null` when the package declares none.

| Argument | Meaning |
|---|---|
| `map` | Exact bare name → scoped name. Tried first. |
| `prefixes` | Bare prefix → scoped prefix, tried in order. An empty bare prefix maps every missing name; that is authkit-preset's shape. |
| `notice` | `DeprecationNotice::TriggerError` (default), `::Log`, or `::None`. |
| `removal` | How the notice words the earliest removal. Defaults to `the next minor after 0.1`. |

## Installing it by hand

```php
use Illuminate\Routing\Router;
use Illuminate\Routing\UrlGenerator;
use Simtabi\Laranail\Package\Tools\Support\Routing\BareRouteNameAliases;

$aliases = BareRouteNameAliases::install(
    router: $this->app->make(Router::class),
    url: $this->app->make(UrlGenerator::class),
    package: 'laranail/authkit-preset',
    prefixes: ['api.' => 'laranail-auth-api.', '' => 'laranail-auth.'],
    notice: DeprecationNotice::None,
);
```

`DeprecationNotice::Log` needs a `logger`, either a `LoggerInterface` or a closure that returns one.
A closure is resolved per notice, so a logger swapped after boot is honoured.

## What it guarantees

- **Chains.** The generator holds one resolver, so installing a second would discard the first. The
  resolver already installed is consulted for every name this one does not own. Its answer is used
  only if it is a string. Anything else means "not resolved".
- **Asks the public Router** whether the scoped route exists, never `UrlGenerator::$routes`.
- **Announces once per name per process.** Whichever notice is chosen, a link rendered on every page
  does not write a line per request. `BareRouteNameAliases::forgetWarnings()` resets this in tests.
- **Fails loudly on a framework change.** Laravel offers a setter for the resolver and no getter, so
  chaining reads the protected `UrlGenerator::$missingNamedRouteResolver`. `previousResolver()` is
  the only place that reads it, and it throws a `LogicException` if the property is gone. A contract
  test in this package pins the property against the installed framework, so a rename upstream
  fails CI here rather than at a user's boot.

## `has()` and `currentIs()`

`Route::has()` and `Request::routeIs()` read the route collection directly and never reach the
resolver. `Route::has('error-pages.preview')` answers false. Laravel 13 has no hook for either, so ask
the installed instance instead:

```php
$aliases = $package->deprecatedRouteNames();

$aliases->has('error-pages.preview');   // true, and announced once
$aliases->currentIs('error-pages.*');   // matches a current laranail-error-pages.* route
$aliases->scopedFor('error-pages.preview'); // 'laranail-error-pages.preview', silent
```

A name or pattern that matches as written answers true without a notice. A deprecated one that
matches only through its scoped form is announced.

## Testing a package that uses it

`AssertsRegisteredNames::assertDeprecatedRouteNamesResolve()` checks each bare name is not a route of
its own and still generates its scoped route's URL. See [Naming assertions](naming-assertions.md).

---

[← Docs index](../../README.md#documentation)
