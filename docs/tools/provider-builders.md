# Provider builders

Fluent builders on the `Package` for the runtime wiring a service provider used to hand-write in `packageBooted()` / `packageRegistered()` — force HTTPS, set the locale, choose pagination views, merge/decorate config, define gates, register route groups and model bindings, and wire events — all declarative, all applied by the provider at the right phase.

## The config-ordering rule

`configurePackage()` runs **before** the package's own config merges, so a builder value read from config at configure time would see `null` or the host value only. Every config-sourced builder therefore defers to boot: pass a `Closure`, or use the explicit `*FromConfig()` variant. The inline `config(...)` forms in a hand-written provider become `useHttpsFromConfig(...)`, `setLocaleFromConfig(...)`, `prefixFromConfig(...)`, `registerRouteModelFromConfig(...)`, etc.

## Runtime tweaks

```php
$package
    ->useHttpsFromConfig('acme.force_ssl', true)   // URL::forceScheme('https')
    ->setLocaleFromConfig('app.locale', 'en')      // Carbon::setLocale + App::setLocale
    ->paginator()->setViews('acme::pagination.default', 'acme::pagination.simple');
```

`useHttps(bool|Closure)` / `setLocale(string|Closure)` take literals or closures; `paginator()` also offers `defaultView()`, `simpleView()`, `useTailwind()`, `useBootstrap()`.

## Config decoration

Two mechanisms, reusing the package's `ConfigMerger` (host-wins) and the config repository:

```php
$package
    // register phase — merge package files as DEFAULTS, host values win
    ->mergesConfigDefaults('config/laravel.php')          // file returns [globalKey => defaults]
    ->mergesConfigDefaults('config/extra.php', 'app')     // single file → one global key
    ->mergesConfigDefaultsFrom('config/third-party')      // every *.php → its basename key
    // boot phase — decoration that depends on runtime data (failure-safe)
    ->configDecorator(fn (ConfigDecorator $c) => $c
        ->when(setting('app_name') !== null, fn ($cc) => $cc->set('app.name', setting('app_name'))));
```

`configDecorator()` closures run at **boot** and are **failure-safe** — a throw is logged to the package's own logfile and skipped, so a decorator reading an unmigrated database can never crash boot. `ConfigDecorator` exposes `set` / `get` / `has` (`exists`/`check`) / `forget` (`remove`/`delete`) / `merge` / `when` / `validate`.

> **Timing caveat.** `configDecorator()` runs at boot, so it is the right home for values that depend on runtime data (settings, the DB). Config that another package reads during **registration** — e.g. `permission.models.*`, or an `auth.providers.users.model` some integration binds at register time — must be set in the register phase instead (a plain `config()->set()` in your `packageRegistered()`, or `mergesConfigDefaults()` for host-wins defaults). A boot-phase decorator would resolve too late.

## Gates

```php
$package->registerGates([
    'manage-session' => fn (User $user, Session $session) => $user->owns($session),
]);
```

Mirrors `registerRateLimiter(s)`; applied via `Gate::define()` at boot.

## Route groups

A route file wrapped in `Route::middleware()->prefix()->group()`, with a route-cache guard the bare group loader otherwise lacks:

```php
use Simtabi\Laranail\Package\Tools\Support\Definitions\RouteGroupDefinition;

$package->registerRouteGroups([
    RouteGroupDefinition::make('routes/web.php')
        ->middlewareFromConfig('acme.routes.web_middleware', ['web']),
    RouteGroupDefinition::make('routes/api.php')
        ->prefixFromConfig('acme.routes.api_prefix', 'api')
        ->middlewareFromConfig('acme.routes.api_middleware', ['api'])
        ->whenConfig('acme.features.api'),
]);
```

Because a literal middleware name and a config key are both strings, the config-resolved values use explicit `*FromConfig()` variants (resolved at boot). Bare `hasRoutes()` is unchanged.

## Route bindings

```php
$package
    ->registerRouteModelFromConfig('role', 'acme.models.role')   // Route::model, class from config
    ->registerRouteModel('user', fn () => $this->userModel())    // Route::model, class from a closure
    ->registerRouteBinding('session', fn ($id) => Session::findOrFail($id)); // Route::bind
```

Mirrors `bootPackageMiddleware(Router)`; applied at boot with the router.

## Events

```php
use App\Listeners\LogUserActivity;

$package->event()
    ->addListeners([
        [Login::class, HandleSuccessfulLogin::class],   // pair form
        Logout::class => HandleLogout::class,            // map form
    ])
    ->addSubscriber(LogUserActivity::class)              // class-string subscriber
    ->addSubscriber(fn (Dispatcher $events) => $events->listen(Failed::class, /* … */)); // closure subscriber
```

`addListeners()` accepts both the pair (`[[Event, Listener], …]`) and map (`[Event => Listener|Listener[]]`) shapes; `addSubscriber()` accepts a class string or a dispatcher-receiving closure.

## View composers

No new builder — reuse the existing `registerViewComposers([...], autoPrefix: false)` (pass `autoPrefix: false` for already-namespaced view names). See [Logging](logging.md) and the configuration reference.

---

[← Docs index](../../README.md#documentation)
