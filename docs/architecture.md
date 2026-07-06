# Architecture

## Overview

`laranail/package-tools` is organised as a service layer behind a fluent
builder. It is a runtime base library: it publishes no `config/*.php` of
its own. A consuming package extends the abstract `PackageServiceProvider`
and describes itself through the fluent `Package` builder.

> **Historical context.** This package was extracted from
> `laranail/packager` (now [`laranail/package-scaffolder`](https://github.com/laranail/package-scaffolder)).
> The scaffolder retains the generator/blueprint tooling; `package-tools`
> is the slimmer runtime half. The two are independent packages — nothing
> in `package-tools` references a `Packager` facade, a `config/packager.php`,
> or any `PACKAGER_*` environment variable.

## Core components

### 1. Package class

`Simtabi\Laranail\Package\Tools\Package` is the central builder. It
aggregates package configuration through 15 domain aggregator traits
under `src/Concerns/Package/`: `ConfiguresConfig`,
`ConfiguresViews`, `ConfiguresAssets`, `ConfiguresRoutes`,
`ConfiguresTranslations`, `ConfiguresDatabase`, `ConfiguresCommands`,
`ConfiguresComponents`, `ConfiguresMiddleware`, `ConfiguresEvents`,
`ConfiguresAuthorization` (policies, morph maps, observers, rate
limiters — added in 2.0), `ConfiguresServiceProviders`,
`ConfiguresComposer`, `ConfiguresHelpers`, and `ConfiguresLifecycle`.
Each aggregator composes its leaf `Has*` traits; all 51 leaf traits
under `src/Concerns/Package/` are wired through an aggregator.

### 2. Service layer (`src/Services/`)

Single-responsibility services, grouped by domain. See
[services.md](services.md) for the full reference with exact
fully-qualified class names. The domains:

- **Asset** — group resolution, publishing, registry, validation.
- **Audit** — `OsvAuditService` (OSV.dev advisories; backs `laranail::package-tools.audit`).
- **Component** — anonymous/class Blade, namespace resolution, registry, validation.
- **Config** — file resolution, merging, get/set, validation, pattern resolution.
- **Database** — seeder registry, discovery, executor, resolver hook.
- **Discovery** — `AttributeDiscoverer` (backs `discoversWithAttributes()`).
- **Doctor** — check contract, result, status enum, runner (backs `laranail::package-tools.doctor`).
- **Event** — event/listener and middleware registries.
- **Facade** — `FacadeAutoGenerator` (backs `laranail::package-tools.ide-helper`).
- **Http** — vendor-neutral HTTP-client option builder (`PKG_HTTP_*`).
- **Package** — Composer operations, dependency resolution, analysis, validation.
- **Sbom** — `SbomGenerator` (CycloneDX 1.5; backs `laranail::package-tools.sbom`).
- **System** — read-only runtime/environment inspector.
- **Utility** — console helper, namespace resolver, path validator, progress indicator.
- **View** — component loader, composer registry, validation.

Plus support classes under `src/Support/` (`ConfigDetector`,
`ConfigGate`, `DeferredCallQueue`, `FluentPackageHelper`,
`ForeignKeyCheckGuard`, `PathResolver`, `RuntimeConfigurator`), the
`Scheduling` pair (`CronBuilder`, `TimeOfDay`), the fluent
`Support\Definitions\*` value objects (scheduled commands, seeders,
about sections, doctor checks, install commands), and the `ErrorStorage`
subsystem.

### 3. Package concerns (`src/Concerns/`)

Traits compose package functionality:

- `Concerns/Package/` — the 15 aggregators + their leaf `Has*` traits.
- `Concerns/PackageServiceProvider/` — the `Process*`/`Loads*` traits that
  the provider mixes in to boot assets, configs, views, routes, commands,
  migrations, components, translations, etc.

### 4. Base command and namespaced names

`Simtabi\Laranail\Package\Tools\Commands\Command` is an abstract base
extending `Illuminate\Console\Command`. It mixes in
`Commands\Concerns\SupportsNamespacedNames`, the trait that lets a
command name use the laranail `::` separator
(`laranail::package-tools.doctor`). Symfony's `Command::validateName()`
rejects the empty segment in `::`, so the trait overrides `setName()` and
`setAliases()` to write the private name/aliases properties directly via a
scope-bound closure, accepting both `::` and `:`. Symfony's `find()`
resolves the exact name before its `:`-splitting lookup, so dispatch is
unaffected. All four built-in commands extend this base; a consumer
opts in by extending it or `use`-ing the trait. See
[tools/command-naming.md](tools/command-naming.md).

### 5. Provider lifecycle

`PackageServiceProvider` orchestrates register and boot. Consumers
override `configurePackage()` (required) plus the optional
`registeringPackage()`, `packageRegistered()`, `bootingPackage()`, and
`packageBooted()` hooks. The `Package` object additionally carries
closure hooks (`onBeforeBoot`, `onAfterBoot`, …). See
[configuration.md](configuration.md) for details.

## Design patterns

### Service pattern

Logic lives in dedicated service classes, each with a single
responsibility.

### Dependency injection

Services are injected through constructors, which keeps them testable.

### Fluent interface

The `Package` class provides a fluent API with method chaining; every
configuration method returns `static`.

### Trait composition

The `Package` class and the abstract provider compose functionality
through traits. The builder uses a two-tier scheme: 15 domain aggregator
traits, each composing a set of leaf `Has*` traits. This keeps a single
domain's surface (config, views, database, …) in one aggregator while the
leaves stay small and individually testable. All leaves are wired through
an aggregator.

## Design intent

These notes record *why* the runtime is shaped the way it is.

### Attribute-driven discovery is the differentiator

The fluent builder surface (`hasConfigFile()`, `hasViews()`, the
lifecycle hooks) is deliberately familiar, so a package author can move
to this base with little friction. The design differentiator is
`discoversWithAttributes()`: classes carrying `#[AsArtisanCommand]`,
`#[AsRoute]`, or `#[AsViewComposer]` are wired automatically, with no
matching `hasCommand()` / `hasViewComposer()` calls. Discovery is
stdlib-only — `AttributeDiscoverer` walks `*.php` files, derives each
FQCN from the PSR-4 root, and reflects the class — so it carries no
third-party auto-discovery dependency. The flag is set in
`configurePackage()`; the scan runs at `packageBooted()`. See
[tools/attribute-discovery.md](tools/attribute-discovery.md).

### Why a definitions layer? (2.x)

The 2.x surface is built on fluent **definition value objects**
(`Support\Definitions\*`): configure time records intent; nothing
touches the framework until the right lifecycle moment. Config gates
(`whenConfig()` / `whenConfigNotNull()`) share one implementation
(`Support\ConfigGate`) and evaluate at boot — or, for schedules, when
the `Schedule` itself resolves — never at configure time, so a package
can be described before the host's config is even merged.
`ScheduledCommandDefinition` splits its vocabulary in two tiers on
purpose: the cron-expressible frequency methods live once on the
standalone `CronBuilder` (a validated designer any code can use), and
every other call is recorded in a generic `DeferredCallQueue` and
replayed — validated, with argument normalization — on the real
scheduler `Event`, so the full Laravel scheduler vocabulary works
without being reimplemented. Morph maps are registered
**non-enforcingly** (no `Relation::requireMorphMap()`): a package
forcing enforcement globally would break unrelated host morphs, so
hosts opt in themselves. See
[configuration.md](configuration.md#scheduled-commands) for the full
behavior reference.

### Seeder auto-run and the package-author plumbing

Package authors register seeders with `hasPackageSeeders()` /
`discoverPackageSeedersIn()`. The design goal is that these run without
the host app editing its own `DatabaseSeeder` — but **never at
application boot** (the pre-2.0 boot-time execution path ran DB writes
per request and was removed). Boot only evaluates each definition's
config gate and registers the surviving bundles; a `SeederResolverHook`
then runs everything registered the first time the host's
`DatabaseSeeder` resolves (e.g. `php artisan db:seed`). The
hook is idempotent — it fires at most once per invocation even when
several packages attach — and uses weak references so it never pins the
registry/executor in memory. Each bundle's seeding runs inside
`ForeignKeyCheckGuard` by default (FK checks off, then restored,
nesting- and exception-safe, scoped per bundle), bundles execute in
priority order (lower first, stable ties), and the
`SeedingStarted` / `SeedingFinished` events fire when a bundle opts in
via `fire_events`. A failing seeder is logged and counted, not
fatal. See [seeding.md](seeding.md) and
[configuration.md](configuration.md#package-seeders).

### Fluent-return convention

Every configuration method on `Package` returns `static`, not `self` or
`void`, so chains compose and subclassing keeps the chained type. The
closure lifecycle hooks (`onBeforeBoot`, `onAfterBoot`, …) follow the
same rule.

### PHP 8.4+ / Laravel 13 target

The package targets PHP `^8.4` (tested on 8.4, 8.5) and Laravel
`^13.0`. This is a deliberate floor, not a constraint inherited from
elsewhere: it lets the code use readonly classes, typed constants, and
current attribute/reflection APIs without back-compat shims, and keeps
the dependency surface to current `illuminate/*` contracts.

## Cross-platform support

### Path resolution

Uses `Support\PathResolver` for consistent path handling across Windows,
Linux, macOS, and WSL, including package-root detection from a provider
file.

### Laravel helpers

Uses Laravel helpers (`File`, `Str`, `Arr`) rather than native PHP
functions where practical.

## Testing strategy

### Isolation harness

`Testing\IsolatedTestCase` wraps Testbench with in-memory SQLite, a sync
queue, and helpers (`assertTableExists`, `assertCommandExists`,
`createTempPath`).

### Unit & integration tests

The Pest suite tests individual services and concerns in isolation, plus
service interactions and full register/boot workflows.

## Open source standards

- PSR-12 coding standards (enforced by Pint).
- PHPStan level 8 (Larastan).
- Rector clean.
- Security auditing via `composer audit` and the `laranail::package-tools.audit` command.

[← Docs index](../README.md#documentation)
