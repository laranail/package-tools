# Services reference

`laranail/package-tools` ships a set of focused, single-responsibility
service and support classes under `Simtabi\Laranail\Package\Tools\`. They
back the fluent `Package` builder, the `laranail::package-tools.*` commands, and the
abstract `PackageServiceProvider`, and are also usable directly from a
consuming package via the container (`app(...)`) or constructor
injection.

There is **no `Packager` facade and no `config/packager.php`** — this is a
runtime library that publishes no config of its own. The class names
below are exact fully-qualified names.

## Asset (`Services\Asset`)

| Class | Purpose |
|---|---|
| `Services\Asset\AssetGroupResolver` | Resolves asset groups into source → target publish paths. |
| `Services\Asset\AssetPublisher` | Orchestrates publishing of package assets with publish tags. |
| `Services\Asset\AssetRegistry` | Tracks published assets and manages cleanup of staged assets. |
| `Services\Asset\AssetValidator` | Validates asset files/directories for existence, readability, and size. |

## Audit (`Services\Audit`)

| Class | Purpose |
|---|---|
| `Services\Audit\OsvAuditService` | Posts `composer.lock` packages to the OSV.dev API and aggregates returned advisories. Backs `laranail::package-tools.audit`. |

## Component (`Services\Component`)

| Class | Purpose |
|---|---|
| `Services\Component\AnonymousComponentLoader` | Loads and registers file-based anonymous Blade components with prefix support. |
| `Services\Component\ComponentNamespaceResolver` | Resolves and normalizes component namespaces. |
| `Services\Component\ComponentRegistry` | Registers Blade, Livewire, and Vue components with type-aware storage. |
| `Services\Component\ComponentValidator` | Validates component classes for existence and instantiability. |

## Config (`Services\Config`)

| Class | Purpose |
|---|---|
| `Services\Config\ConfigFileResolver` | Resolves config file paths, including nested config directories. |
| `Services\Config\ConfigMerger` | Merge strategies for config arrays (deep, replace, append). |
| `Services\Config\ConfigService` | Core config operations: merge, set, get, has, forget. |
| `Services\Config\ConfigValidator` | Validates config files/arrays for parseability and structure. |
| `Services\Config\PatternResolver` | Resolves dynamic patterns with variable substitution. |

## Database (`Services\Database`)

| Class | Purpose |
|---|---|
| `Services\Database\SeederBuilder` | Fluent discovery + filtering + register/execute (`from`, `classes`, `only`, `except`, `execute`). |
| `Services\Database\SeederBundle` | One package's registered bundle: typed, per-bundle-isolated options (FK guard, events, parameters, priority). |
| `Services\Database\SeederConsoleFormatter` | Optional tree-structured, colourised console output for a seeding run (contract: `Contracts\SeederConsoleFormatterInterface`). |
| `Services\Database\SeederExecutor` | Executes registered bundles in priority order, with per-bundle FK toggling and event emission. |
| `Services\Database\SeederManager` | Standalone entry point (`autoSeed` / `seeders` / `run` / `registry`); backs the `PackageSeeder` facade. |
| `Services\Database\SeederPathDiscoverer` | Discovers seeder classes from PHP files via tokenization (no autoloader needed). |
| `Services\Database\SeederRegistry` | In-memory store of per-package `SeederBundle`s. |
| `Services\Database\SeederResolverHook` | Hooks the container so registered seeders run when `DatabaseSeeder` resolves. |

The seeding subsystem end to end — both the `Package` builder path and
the standalone path — is documented in [seeding.md](seeding.md).

## Discovery (`Services\Discovery`)

| Class | Purpose |
|---|---|
| `Services\Discovery\AttributeDiscoverer` | Walks a directory tree and yields `ReflectionClass` objects for classes carrying given attributes. Backs `discoversWithAttributes()`. |

## Doctor (`Services\Doctor`)

| Class | Purpose |
|---|---|
| `Services\Doctor\DoctorCheck` | Contract for a single health-check unit. |
| `Services\Doctor\DoctorReporter` | Static table/JSON renderer for a doctor run: summary + conventional exit code. |
| `Services\Doctor\DoctorResult` | Outcome of one check: status, message, optional detail. `Arrayable`. |
| `Services\Doctor\DoctorService` | Runs registered checks (with optional per-check group attribution and (group, name) dedup) and produces a report with pass/warn/fail/skip counts. Backs `laranail::package-tools.doctor`. |
| `Services\Doctor\DoctorStatus` | Enum of check outcomes (Pass, Warn, Fail, Skip) with ANSI color mapping. |
| `Services\Doctor\HealthResponder` | Static one-liner for HTTP health endpoints: `200 healthy` / `503 degraded` JSON. |
| `Services\Doctor\Checks\*` | The bundled parameterised check library (`PhpVersionCheck`, `PhpExtensionCheck`, `ConfigPresentCheck`, `WritablePathCheck`, `ReachabilityCheck`, `SoftDependencyCheck`, `CallbackCheck`). |

The check library, the fluent `DoctorCheckDefinition`, and the render
shapes are documented in [tools/doctor.md](tools/doctor.md).

## Event (`Services\Event`)

| Class | Purpose |
|---|---|
| `Services\Event\EventRegistry` | Registers event listeners and subscribers with per-event tracking. |
| `Services\Event\MiddlewareRegistry` | Registers middleware, aliases, and middleware groups. |

## Facade (`Services\Facade`)

| Class | Purpose |
|---|---|
| `Services\Facade\FacadeAutoGenerator` | Walks classes carrying `#[AsFacade]` and generates Laravel facade subclasses with `@method` docblocks. Backs `laranail::package-tools.ide-helper`. |

## Http (`Services\Http`)

| Class | Purpose |
|---|---|
| `Services\Http\HttpConfigurationService` | Fluent builder for HTTP-client option arrays, defaulted from `PKG_HTTP_*` env vars; vendor-neutral output via `toGuzzleConfig()`. |
| `Services\Http\Contracts\HttpConfigurationServiceInterface` | Contract for the HTTP-client configuration builder. |

The `PKG_HTTP_*` defaults are documented in
[configuration.md](configuration.md#runtime-environment-variables) and
[tools/runtime-services.md](tools/runtime-services.md).

## Package (`Services\Package`)

| Class | Purpose |
|---|---|
| `Services\Package\ComposerService` | Runs Composer commands (install, update, require, remove, validate) and reports results. |
| `Services\Package\DependencyResolver` | Resolves package dependencies and version constraints from `composer.json`. |
| `Services\Package\PackageAnalyzer` | Analyzes package structure, metrics, and `composer.json` composition. |
| `Services\Package\PackageValidator` | Validates package structure, naming, and PSR-4 namespace compliance. |

## Sbom (`Services\Sbom`)

| Class | Purpose |
|---|---|
| `Services\Sbom\SbomGenerator` | Pure-PHP CycloneDX 1.5 JSON SBOM generator from `composer.json` / `composer.lock`. Backs `laranail::package-tools.sbom`. |

## System (`Services\System`)

| Class | Purpose |
|---|---|
| `Services\System\SystemService` | Read-only inspector for PHP runtime, Laravel context, and server environment. |
| `Services\System\Contracts\SystemServiceInterface` | Contract for the system inspector. |

## Utility (`Services\Utility`)

| Class | Purpose |
|---|---|
| `Services\Utility\ConsoleHelper` | Formatted console output: tables, messages, lists, sections. |
| `Services\Utility\NamespaceResolver` | Transforms namespaces between dashed, dotted, and PSR-4 forms. |
| `Services\Utility\PathValidator` | Validates paths for security and cross-platform compatibility (traversal/injection checks). |
| `Services\Utility\ProgressIndicator` | CLI progress bars and spinners. |

## View (`Services\View`)

| Class | Purpose |
|---|---|
| `Services\View\ViewComponentLoader` | Loads and registers view components from paths/namespaces. |
| `Services\View\ViewComposerRegistry` | Registers view composers and creators for data injection. |
| `Services\View\ViewValidator` | Validates view paths, namespaces, and configurations. |

## Support (`Support`)

| Class | Purpose |
|---|---|
| `Support\ConfigDetector` | Auto-detects project namespace, vendor, and package name from `composer.json`. |
| `Support\ConfigGate` | The single config-gating implementation behind every `whenConfig()` / `whenConfigNotNull()`: truthy and not-null modes, evaluated at `passes()` time. |
| `Support\DeferredCallQueue` | Generic capture/replay of fluent calls onto a later target, with recursive argument normalization (`BackedEnum` → value, `TimeOfDay` → `'H:i'`, `CronExpressible` → expression, arrays recursed) and unknown-method validation. |
| `Support\FluentPackageHelper` | Fluent helper for package-specific operations (config, routes, assets, views, translations). |
| `Support\ForeignKeyCheckGuard` | Disables FK-constraint enforcement around a callback, with safe nesting and exception-safe restoration. |
| `Support\GateMode` | Enum of the two `ConfigGate` modes (`Truthy`, `NotNull`). |
| `Support\PathResolver` | Cross-platform path resolution: normalization, traversal protection, package-root detection. |
| `Support\RuntimeConfigurator` | Fluent PHP runtime configuration (memory, timeouts, error reporting, debug tooling). |

## Support / Scheduling (`Support\Scheduling`)

| Class | Purpose |
|---|---|
| `Support\Scheduling\CronBuilder` | Standalone, validated cron-expression designer (implements `Contracts\CronExpressible`); the single home of the cron-expressible frequency vocabulary. |
| `Support\Scheduling\TimeOfDay` | Fluent time-of-day value: 24h/12h parsing, `am()`/`pm()`, minute arithmetic with midnight wrap, canonical `format24()`. |

## Support / Definitions (`Support\Definitions`)

Fluent value objects consumed by the `Package` builder — see
[configuration.md](configuration.md) for the behavior reference of each.

| Class | Purpose |
|---|---|
| `Support\Definitions\AboutSectionDefinition` | A `php artisan about` section: per-field lazy closures, `fieldsUsing()` bulk sources, config gates. |
| `Support\Definitions\AutoSeederDefinition` | A package's seeder bundle for `db:seed`-time execution: explicit list or discovery, ignore list, gates, priority. |
| `Support\Definitions\DoctorCheckDefinition` | A fluent `DoctorCheck` wrapper: bundled-library factories, `named()`/`describe()`, config gates. |
| `Support\Definitions\InstallCommandDefinition` | A fluent install command: named steps in declaration order, built lazily and console-only. |
| `Support\Definitions\ScheduledCommandDefinition` | A scheduled package command: two-tier dispatch over `CronBuilder` + `DeferredCallQueue`, cadences, config gates. |

## Support / ErrorStorage (`Support\ErrorStorage`)

| Class | Purpose |
|---|---|
| `Support\ErrorStorage\ErrorStorageService` | In-memory error bag for collecting non-fatal problems. |
| `Support\ErrorStorage\Contracts\ErrorStorageServiceInterface` | Contract for the error bag, used by install commands and providers. |

## Support / Concerns (`Support\Concerns`)

| Trait | Purpose |
|---|---|
| `Support\Concerns\HasErrorStorage` | Proxies a host class to the container-bound `ErrorStorageService`. |
| `Support\Concerns\HasGuzzleConfig` | Exposes a singleton `HttpConfigurationService` for preconfigured HTTP options. |

## Using a service

Services are plain classes — resolve them from the container or inject
them. For example, the runtime system inspector:

```php
use Simtabi\Laranail\Package\Tools\Services\System\Contracts\SystemServiceInterface;

class MyCheck
{
    public function __construct(private SystemServiceInterface $system) {}
}
```

The three runtime services bound by the provider
(`SystemService`, `HttpConfigurationService`, `ErrorStorageService`)
should be resolved through their interfaces — see
[tools/runtime-services.md](tools/runtime-services.md).

## See also

- [architecture.md](architecture.md) — the service-layer domains at a
  glance.
- [configuration.md](configuration.md) — the fluent `Package` builder
  these services back.
- [Tools & features](../README.md#documentation) — per-command and per-feature deep dives.

[← Docs index](../README.md#documentation)
