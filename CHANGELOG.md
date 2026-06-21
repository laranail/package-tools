# Changelog

All notable changes to `laranail/package-tools` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.5.0] - 2026-06-21

This release consolidates duplicate/unwired builder traits into their wired
siblings (no public builder method removed) and, in doing so, wires up several
capabilities that were previously dead, plus a real bugfix.

### Added

- **Event auto-discovery + bulk helpers** — `discoverEventListeners(dir, namespace)`
  maps listeners to events by the typed first parameter of `handle()`/`__invoke()`;
  `registerEventListeners()` / `registerEventSubscribers()` register in bulk.
- **Richer view composers** — `registerViewComposer()` now accepts a `callable`;
  added `registerViewComposers()`, `registerGlobalViewComposer()`,
  `registerViewCreator()`, `registerViewComposerWithDependencies()`.
- **Asset conveniences** — typed `publishModuleJs/Css/Media/Vendors()` +
  `publishAllModuleAssets()`, and a declarative group registry
  (`declareAssetGroup(s)`, `declareStandardAssetGroups()`,
  `declareCustomAssetGroup()`, `getDeclaredAssetGroups()`).
- **Command auto-discovery** — `autoLoadCommands()` registers
  `Illuminate\Console\Command` subclasses found under `src/Commands`.
- Namespace getters are now memoized (`warmNamespaceCache()` / `clearNamespaceCache()`).

### Fixed

- **Factory resolution no longer breaks the host app** — the package's global
  `Factory::guessFactoryNamesUsing` resolver returned `null` for models outside
  the package (breaking the app's own factories); it now falls back to Laravel's
  conventional factory name.
- **Previously-dead wiring activated** — enhanced view composers (registry, global
  composers, creators) and the asset registry / declarative groups are now
  actually registered/published at boot; they were never invoked before.

### Changed

- **Consolidated duplicate traits** (no public builder method lost): events unified
  in a new `ConfiguresEvents`/`HasEventManagement` (removed from the middleware
  trait); namespace caching folded into `HasConfigNamespace`; view-composer and
  asset-group/module capabilities folded into `HasEnhancedViewComposers` /
  `HasAssetPublisher`. Removed the now-redundant unwired duplicate traits
  `HasEventSystem`, `HasCachedNamespaces`, `HasViewComposerRegistry`,
  `HasAssetGroups`, `HasModuleAssets`.

### Internal

- Replaced container array-access with typed `make()`, removed dead defensive
  guards, and shrank the PHPStan baseline from 18 entries to 2.

## [0.4.1] - 2026-06-21

### Added

- **Read-and-return config helpers** — `Package::loadConfigData(folder, recursive)`,
  `Services\Config\ConfigService::loadFrom(baseDir, folder, recursive)`, and
  `Services\Config\ConfigFileResolver::load()` / `loadAll()`. The counterpart of
  `discoversConfig()`: read nested config files and **return** them as
  `[dottedKey => array]` (same folder→key mapping) without registering anything
  in the config repository. Raw data (no `__namespace` processing); a missing
  folder yields `[]`; an unreadable or non-array file throws
  `Exceptions\InvalidPath`.

## [0.4.0] - 2026-06-21

### Changed

- **BREAKING — PHP namespace renamed** `Simtabi\Laranail\PackageTools` →
  `Simtabi\Laranail\Package\Tools`. Every consumer `use` import must be updated
  (the composer/package slug `laranail/package-tools` and the
  `laranail::package-tools.*` command names are unchanged).
- **BREAKING — service provider renamed** `…\Providers\LaranailToolsServiceProvider`
  → `…\Providers\PackageToolsServiceProvider`. Auto-discovery handles this; only
  apps that register the provider manually (or reference its FQN) need to update.
  The `PackageSeeder` facade and the abstract `PackageServiceProvider` base are
  unchanged apart from the namespace prefix.

## [0.3.2] - 2026-06-21

### Fixed

- `Services\Database\SeederConsoleFormatter`: seeder names now render in full
  (`PostSeeder` → `Post`, was `Pos` — an off-by-one in the suffix trim);
  correct pluralisation in the summary ("1 failure", not "1 failures"); status
  glyphs and tree branches degrade to ASCII on non-Unicode terminals via
  console's capability-aware `Symbols` (was hardcoded Unicode).
- Requires `laranail/console ^1.2.1`, which fixes a signal-handling NPE when a
  command is constructed outside a running Application (this had broken
  larastan static analysis on CI).

## [0.3.1] - 2026-06-21

### Fixed

- With config namespacing on, a flat config file now publishes to the
  namespace-matching nested path (`config/vendor/package.php`) so the published
  override loads under the same dotted key it was merged into
  (`config('vendor.package')`). Previously it published to a flat
  `config/package.php`, which Laravel loaded under the wrong key.

## [0.3.0] - 2026-06-21

### Added

- **Namespaced / folder-tree config resolution** — config files in sub-folders
  mount at dotted keys (`config/admin/panel.php` → `config('admin.panel.*')`).
  New builder methods `hasNestedConfig()`, `hasNestedConfigs()`,
  `hasConfigDirectory()` and the recursive `discoversConfig()`; an optional
  in-file `__namespace` key overrides the mount point (stripped before merge).
  Reads stay native `config()`; flat `hasConfigFile()` is unchanged. See
  [config-namespacing](docs/tools/config-namespacing.md).

### Changed

- **BREAKING — PHP floor raised to `^8.4`** (was `^8.3`). `laranail/console` 1.x
  requires PHP `^8.4.1`, so package-tools can no longer support PHP 8.3; the CI
  matrix is now `8.4 / 8.5`.
- `Services\Database\SeederConsoleFormatter` migrated to the `laranail/console`
  1.x umbrella (its `ConsoleUIFormatter`/`Widgets`); `laranail/console` is now
  required at `^1.0` and resolves from Packagist (the dev vcs bridge is gone).

## [0.2.0] - 2026-06-19

Initial release — a runtime base library for building Laravel packages.

### Added

**Package builder & discovery**

- Fluent `Package` builder + abstract `PackageServiceProvider` (13 domain
  aggregator traits wiring 44 leaf traits).
- Attribute-driven discovery (`#[AsArtisanCommand]`, `#[AsRoute]`, `#[AsFacade]`,
  `#[AsViewComposer]`), wired via `Package::discoversWithAttributes()`.
- `bootPackageDeferredHooks()` wires middleware aliases/groups, event listeners
  and subscribers, factories, and seeders during boot.

**Artisan commands** (named `laranail::package-tools.<command>`)

- `…doctor` — runs every registered `DoctorCheck`; `--json` / `--strict`.
- `…sbom` — CycloneDX 1.5 JSON SBOM for `composer.lock` (pure PHP); refuses
  `--output` paths that escape the project root.
- `…audit` — posts `composer.lock` to OSV.dev `/v1/querybatch`; exits non-zero
  on advisories.
- `…ide-helper` + `FacadeAutoGenerator` — emits Facade subclasses with `@method`
  docblocks from `#[AsFacade]` contracts.
- `Commands\Command` + `Commands\Concerns\SupportsNamespacedNames` enable the
  `::` namespace separator (both `::` and `:` are accepted).

**Database seeding** (the single home for laranail seeding)

- Standalone API: the `PackageSeeder` facade + `Services\Database\SeederManager`,
  and a fluent `Services\Database\SeederBuilder`
  (`from()`/`classes()`/`only()`/`except()`/`execute()`).
- `SeederExecutor` runs with a foreign-key guard and lifecycle events
  (`SeedingStarted`/`SeedingFinished`, `SeederExecuting`/`SeederExecuted`/`SeederFailed`)
  and returns a typed, immutable `ValueObjects\SeederExecutionStats`.
- Optional tree-structured console output via
  `Services\Database\SeederConsoleFormatter` (styled with `laranail/console`).
- Dedicated `Exceptions\SeederException`.

**Runtime services & testing**

- `Testing\IsolatedTestCase` — opinionated Testbench wrapper (in-memory SQLite,
  sync queue, `assertTableExists` / `assertCommandExists` / `createTempPath`).
- Abstract HTTP base controllers (`WebController`, `ApiController` with typed
  `JsonResponse` helpers).
- `SystemService`, `HttpConfigurationService`, `ErrorStorageService` (+ contracts)
  with the `HasErrorStorage` / `HasGuzzleConfig` consumer traits.

### Security

- `askToStarRepoOnGitHub()` validates the repo slug and opens the URL through a
  shell-less process (no command injection).
- `ConfigFileResolver` rejects `..`, null bytes, and absolute paths so config
  resolution cannot escape the package `config/` directory.
- `FacadeAutoGenerator` validates `#[AsFacade]` alias/accessor/namespace against a
  PHP-identifier regex (no code injection).
- `OsvAuditService` strips ANSI/control characters from network-supplied text,
  `rawurlencode`s OSV ids, and bounds-checks the timeout (1–600s).
- Runtime services route filesystem I/O through `File::` and HTTP through `Http::`.

### Dependencies

- PHP `^8.3 || ^8.4 || ^8.5`, Laravel `^13.0`.
- `laranail/console: ^0.1`, `symfony/process: ^7.0`.
