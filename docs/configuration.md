# Configuration

`laranail/package-tools` is a runtime base library. It publishes **no
`config/*.php` of its own** — a consuming package does not configure
`package-tools` through a config file or `vendor:publish`. Instead, the
consumer extends the abstract `PackageServiceProvider` and describes its
own package fluently inside `configurePackage(Package $package)`.

```php
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\PackageServiceProvider;

final class FooServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('vendor/foo')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_foos_table')
            ->hasInstallCommand(fn ($cmd) => $cmd->publishConfigFile()->askToRunMigrations())
            ->discoversWithAttributes()
            ->hasDoctorCheck(MyHealthCheck::class);
    }
}
```

The provider does the rest: it instantiates the `Package`, resolves the
package base path from the provider file location (`setPathFrom`), calls
`configurePackage()`, validates that a name and base path are set, loads
helpers, merges configs, then boots assets, views, routes, commands,
migrations, components, translations and the lifecycle hooks.

## The `Package` builder

Every fluent method returns `static`, so calls chain. The aggregator
traits live under `src/Concerns/Package/`; the methods below are the
public surface a consumer calls. Names and signatures are exact.

### Identity & paths

| Method | Purpose |
|---|---|
| `name(string $name, ?callable $transformer = null)` | Set the package name. **Vendor/package format is required** (e.g. `vendor/foo`); the vendor segment becomes `configVendor` and drives the config namespace. An optional transformer rewrites the short name. |
| `setName(...)` | Alias of `name()`. |
| `setPathFrom(string\|object $source, ?int $levelsUp = null)` | Set the package base path from a path string, a provider object (`$this`), or a class name. `levelsUp` defaults to 3 (provider in `src/Providers/`). The provider calls this automatically; override only for unusual layouts. |
| `setPublishTagId(string $id)` / `buildPublishTag(string $name, string $separator = '::')` | Set the base publish-tag id and build namespaced publish tags (`laranail::config`). Allowed separators: `::`, `:`, `-`. |

### Config

| Method | Purpose |
|---|---|
| `hasConfigFile(string\|array\|null $configFileName = null)` | Register one or more **flat** config files (`config/foo.php` → `config('foo.*')`). |
| `hasNestedConfig(string $fileName, string $folder = '', ?string $key = null)` | Mount a config file in a sub-folder at a folder-derived dotted key (`config/admin/panel.php` → `config('admin.panel.*')`). |
| `hasNestedConfigs(array $files, string $folder = '')` | Mount several files from the same sub-folder. |
| `hasConfigDirectory(string $folder)` | Mount every file directly in a sub-folder (one level). |
| `discoversConfig(string $namespace = '', string $folder = '')` | Recursively mount the whole config tree by folder path (optional root namespace). |
| `mergeConfigInto(string $sourceKey, string $targetKey, bool $deep = true)` | Merge one config key into another. |
| `mergeConfigGlobal(string $path, string $globalKey)` / `mergeConfigsGlobal(array $configs)` | Merge package config into a host (global) config key. |
| `setConfig(string $key, mixed $value)` / `setConfigs(array $values)` / `forgetConfig(string $key)` | Imperative config writes. |
| `enableConfigSafeMode()` / `disableConfigSafeMode()` | Toggle safe-mode merging. |

Beyond flat files, config files in sub-folders resolve to dotted keys
(`config('admin.panel.*')`) and a file may optionally declare its own mount with
an `__namespace` key — see **[Namespaced & nested config](tools/config-namespacing.md)**
for the folder→key mapping, precedence, publishing and merge semantics.

### Views, components & assets

| Method | Purpose |
|---|---|
| `hasViews(?string $namespace = null)` | Register the package view namespace. |
| `hasViewComposer(string\|array $view, Closure\|string $viewComposer)` | Bind a view composer. |
| `sharesDataWithAllViews(string $name, mixed $value)` | Share a value with every package view. |
| `hasViewComponent(string $prefix, string $viewComponentName)` / `hasViewComponents(string $prefix, string ...$names)` | Register class-based Blade components. |
| `hasAnonymousComponents(string $path, ?string $prefix = null)` / `discoverAnonymousComponents(string $baseDir = 'resources/views/components')` | Register or auto-discover anonymous Blade components. |
| `hasComponentNamespace(string $namespace, ?string $prefix = null)` / `hasComponentNamespaces(array $namespaces)` | Register Blade component namespaces. |
| `hasBladeDirective(string $name, Closure $handler)` / `hasBladeDirectives(array\|Closure $directives)` | Register Blade directives. |
| `hasInertiaComponents(?string $namespace = null)` | Register Inertia components. |
| `hasLivewireComponent(string $name, string $class)` / `hasLivewireComponents(array\|Closure $components)` | Register Livewire components. |
| `hasVueComponent(string $name, string $path)` / `hasVueComponents(array $components)` / `hasVueComponentsDirectory(string $directory, ?string $namespace = null)` | Register Vue components. |
| `hasAssets()` | Register the package assets directory for publishing. |
| `publishAssets(string $source, string $destination, bool $cleanBeforePublish = false, ?string $tag = null)` | Register a source → destination asset publish path. |
| `publishAssetGroup(string $groupName, array $assets, bool $cleanBeforePublish = false)` / `publishAssetGroups(array $groups, ...)` | Publish named asset groups. |

### Routes, translations, middleware & events

| Method | Purpose |
|---|---|
| `hasRoute(string $routeFileName)` / `hasRoutes(string\|array ...$routeFileNames)` | Register route files. |
| `hasTranslations()` | Register the package translations directory. |
| `registerRouteMiddleware(string $name, string $class)` / `registerGlobalMiddleware(string $class)` | Register middleware. |
| `registerMiddlewareAlias(string $alias, string $class)` / `registerMiddlewareGroup(string $group, array $middleware)` | Register middleware aliases and groups. |
| `registerEventListener(string $event, string $listener)` / `registerEventSubscriber(string $subscriber)` | Register event listeners and subscribers. |

### Database

| Method | Purpose |
|---|---|
| `hasMigration(string $migrationFileName)` / `hasMigrations(string\|array ...$names)` | Register migration files. |
| `runsMigrations(bool $runsMigrations = true)` | Auto-run package migrations. |
| `discoversMigrations(bool $discoversMigrations = true, string $path = '/database/migrations')` | Auto-discover migrations from a path. |
| `loadFactoriesFrom(string $path)` / `loadSeedersFrom(string $path)` | Load factories / seeders from a path. |
| `registerSeeder(string $seederClass)` | Register a single seeder. |
| `hasPackageSeeders(string $key, array $seeders, ?string $namespace = null, array $options = [])` / `discoverPackageSeedersIn(string $path, ?string $namespace = null, array $options = [])` | Register or discover per-package seeders. See [Package seeders](#package-seeders) below. |

#### Package seeders

`hasPackageSeeders()` registers a bundle of `Illuminate\Database\Seeder`
classes against a `SeederRegistry`; `discoverPackageSeedersIn()` tokenises
the `*.php` files under a path (no autoloader needed) and registers the
`Seeder` subclasses it finds. Both take the same `$options` array:

| Option key | Type | Default | Effect |
|---|---|---|---|
| `disable_foreign_key_checks` | bool | `true` | Wrap the run in `ForeignKeyCheckGuard` so FK constraints are off during seeding and restored after (nesting-safe, exception-safe). |
| `fire_events` | bool | `false` | Dispatch `Events\SeedingStarted` before and `Events\SeedingFinished` after the run. |
| `parameters` | array | `[]` | Passed to the constructor of any seeder that declares constructor arguments; parameterless seeders are resolved via the container. |

The registered seeders run two ways: at `packageBooted()` time the
provider executes them through a `SeederExecutor`, and a
`SeederResolverHook` also runs them the first time the host app's
`Database\Seeders\DatabaseSeeder` resolves (typically `php artisan
db:seed` with no `--class`). The hook is idempotent and fires at most
once per invocation. A seeder that throws is logged and counted as a
failure without aborting the rest of the run.

`SeederExecutor::run()` returns a typed
`ValueObjects\SeederExecutionStats` (`total`, `success`, `failed`,
`totalTime`, `errors`, plus helpers like `getSuccessRate()`,
`getFormattedTotalTime()`, `getSummary()`).

Events: with `fire_events`, `Events\SeedingStarted` (carries `array
$packages`) fires before the batch and `Events\SeedingFinished` (carries
`array $packages`, `int $successCount`, `int $failureCount`) after.
Per-seeder events also fire: `Events\SeederExecuting`,
`Events\SeederExecuted` (with `durationMs`), and `Events\SeederFailed`
(with the `Throwable`).

You do **not** need the `Package` builder to use seeding — there is a
standalone API (`PackageSeeder` facade / `SeederManager`) and a fluent
`SeederBuilder`. See **[Seeding](seeding.md)** for the full reference.

```php
$package->hasPackageSeeders(
    key: 'Acme\\Hello',
    seeders: [\Acme\Hello\Database\Seeders\HelloSeeder::class],
    namespace: 'Acme\\Hello',
    options: ['fire_events' => true],
);
```

### Commands

| Method | Purpose |
|---|---|
| `hasCommand(string $commandClassName)` / `hasCommands(string\|array ...$names)` | Register Artisan commands. |
| `hasConsoleCommand(string $commandClassName)` / `hasConsoleCommands(...)` | Register console-only commands. |
| `hasInstallCommand(callable $callable)` | Register an interactive install command (e.g. `->publishConfigFile()->askToRunMigrations()`). |

The closure passed to `hasInstallCommand()` receives the install command
and can chain these steps (`src/Commands/Concerns/`):
`publishConfigFile()`, `publishAssets()`, `publishMigrations()`,
`askToRunMigrations()`, `askToStarRepoOnGitHub(string $vendorSlashRepo, bool $defaultAnswer = false)`,
`copyAndRegisterServiceProviderInApp()`, `startWith(callable)`, and
`endWith(callable)`.

### Service providers & helpers

| Method | Purpose |
|---|---|
| `publishesServiceProvider(string $providerName)` | Publish a stub service provider into the host app. |
| `loadHelpers()` | Load the package `helpers/` directory. (The provider also auto-loads helpers when the directory exists.) |

### Extensions

| Method | Purpose |
|---|---|
| `discoversWithAttributes(?string $directory = null, ?string $namespace = null)` | Scan `src/` for classes carrying `#[AsArtisanCommand]`, `#[AsRoute]`, `#[AsViewComposer]` and wire them automatically. Defaults to the package `src/` and detected namespace. |
| `hasDoctorCheck(string\|DoctorCheck $check)` | Register a `DoctorCheck` for `php artisan laranail::package-tools.doctor`. |

## Lifecycle hooks

There are two ways to run code at well-defined points in the package
lifecycle.

### 1. Override hooks on the provider

`PackageServiceProvider` exposes five overridable methods. Laravel runs
*all* providers' register phase before *any* boot phase.

| Hook | Phase | Use for |
|---|---|---|
| `registeringPackage()` | Register (first) | Log channels, Horizon supervisors, morph maps, child providers — infrastructure that must exist before anything uses it. |
| `configurePackage(Package $package)` | Register | The package definition (name, paths, features). **Abstract — required.** |
| `packageRegistered()` | Register (after config merge) | Container singletons, bindings, aliases, facade backing classes. |
| `bootingPackage()` | Boot (before auto-boot) | Middleware, route-model bindings, Blade namespaces, gates/policies. |
| `packageBooted()` | Boot (last) | Event listeners, scheduler tasks, cross-package integrations, cache warming. |

Between `bootingPackage()` and `packageBooted()` the provider auto-boots
assets, Blade components and directives, commands, configs, Inertia,
Livewire, migrations, routes, child providers, translations, views, view
composers, shared view data, custom publishes, and the deferred Package
hooks (middleware, event listeners/subscribers, factories, seeders).

### 2. Closure hooks on the `Package`

The `HasLifecycleHooks` trait
(`src/Concerns/Package/HasLifecycleHooks.php`) lets `configurePackage()`
register closures that receive the `Package` instance. Each returns
`static`, so they chain:

| Method | Fires |
|---|---|
| `onBeforeRegister(Closure $cb)` / `onAfterRegister(Closure $cb)` | Around package registration. |
| `onBeforeBoot(Closure $cb)` / `onAfterBoot(Closure $cb)` | Around package boot. |
| `onBeforeConfigLoad(Closure $cb)` / `onAfterConfigLoad(Closure $cb)` | Around config loading. |
| `onBeforeViewLoad(Closure $cb)` / `onAfterViewLoad(Closure $cb)` | Around view loading. |

```php
$package->onAfterBoot(function (Package $package) {
    Log::info('Booted package: ' . $package->name);
});
```

`getRegisteredHooks()` returns a per-hook count, primarily for tests.

## Runtime environment variables

`package-tools` reads **no** env vars for its own configuration. The
only env vars it consults are read by
`Simtabi\Laranail\Package\Tools\Services\Http\HttpConfigurationService`,
which is an opt-in fluent builder for HTTP-client option arrays. Defaults
are applied when the variable is unset or empty; every value can also be
set fluently in code (constructor args or setters), which overrides the
env default.

| Variable | Type | Default | Builder accessor |
|---|---|---|---|
| `PKG_HTTP_PERSIST_CONNECTION` | bool | `true` | `setPersistConnection()` / `isPersistConnection()` |
| `PKG_HTTP_REQUEST_TIMEOUT` | int (seconds, `>= 0`) | `60` | `setRequestTimeout()` / `getRequestTimeout()` |
| `PKG_HTTP_MAX_RETRIES` | int (`>= 0`) | `10` | `setMaxRetries()` / `getMaxRetries()` |
| `PKG_HTTP_CACHE_TTL` | int (seconds, `>= 0`) | `10` | `setCacheTtl()` / `getCacheTtl()` |
| `PKG_HTTP_BASE_URI` | string | `null` | `setBaseUri()` / `getBaseUri()` |
| `PKG_HTTP_PROXY` | string | `null` | `setProxy()` / `getProxy()` |

`toGuzzleConfig()` returns an array keyed compatibly with both Guzzle and
Laravel's `Http::withOptions()`. The builder pulls in no HTTP client of
its own. Details and a usage example are in
[tools/runtime-services.md](tools/runtime-services.md).

## Worked example

[`examples/`](examples/) is a cohesive `Acme\Hello` package that exercises
every package-tools feature end to end. Start with the provider, which ties
the rest together:

- [HelloPackageServiceProvider.php](examples/HelloPackageServiceProvider.php)
  — the fluent builder (config, views, components, translations, assets,
  routes, migrations, commands), the install-command flow, package seeders,
  attribute discovery, two doctor checks, and both override and closure
  lifecycle hooks.
- [Console/HelloCommand.php](examples/Console/HelloCommand.php) — a command
  registered via `hasCommand()`.
- [Database/Seeders/GreetingSeeder.php](examples/Database/Seeders/GreetingSeeder.php)
  — a seeder run through `hasPackageSeeders()`, with the
  `SeedingStarted`/`SeedingFinished` events noted inline.
- [Doctor/HelloHealthCheck.php](examples/Doctor/HelloHealthCheck.php) — the
  `DoctorCheck` wired via `hasDoctorCheck()`.

## See also

- [tools/attribute-discovery.md](tools/attribute-discovery.md) —
  `discoversWithAttributes()` in depth.
- [tools/doctor.md](tools/doctor.md) — the `DoctorCheck` contract behind
  `hasDoctorCheck()`.
- [architecture.md](architecture.md) — the aggregator-trait structure
  underneath the builder.

[← Docs index](../README.md#documentation)
