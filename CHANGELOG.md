# Changelog

All notable changes to `laranail/package-tools` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [4.1.0] - 2026-07-10

### Added

- **Fluent `RateLimiterDefinition`** — a reusable, extensible builder for the
  named-rate-limiter boilerplate every throttling package hand-rolls (resolve
  an attempt count, build a throttle key, return a `Limit`). Windows
  (`perSecond`/`perMinute`/`perMinutes`/`perHour`/`perDay`/`unlimited`, each
  taking a fixed `int` or a per-request `Closure`), key shortcuts
  (`by`/`byIp`/`byField`/`bySessionKey` [session-guarded] /`byUser`), a
  `response()` callback, multi-window composition, and a `using()` escape
  hatch. `HasRateLimiters::registerRateLimiter()` now accepts a
  `RateLimiterDefinition` (registered under its own name) alongside the
  unchanged raw `(string $name, Closure)` form. See
  [`docs/tools/rate-limiters.md`](docs/tools/rate-limiters.md). Fully
  backward compatible — raw closures are stored and wired exactly as before.

## [4.0.1] - 2026-07-10

### Fixed

- **`ConfigDecorator::when()` accepts a chaining callback.** Its callback was
  typed `Closure(self): void`, which flagged the natural fluent form
  `fn ($c) => $c->set(...)` (where `set()` returns the decorator) under
  stricter static analysis in consuming packages. The callback's return is
  ignored, so the type is now `Closure(self): mixed`. Docblock-only — no
  runtime change.

## [4.0.0] - 2026-07-10

### Added

- **`PackageAction{Started,Succeeded,Failed}` lifecycle events** — one
  reusable, serialization-safe event family for every package action
  (migrations, seeders, jobs, schedules, installs, custom work), routed
  through the new **`PackageActions` facade** so a report/observe is reachable
  anywhere without a provider. `PackageActionType` and `FailureReason`
  (`Failed` / `Interrupted` / `Cancelled` / `TimedOut` / `Unknown`, with
  `fromThrowable()`) carry the taxonomy. `HandlesPackageActionFailure` is a
  per-type listener base; `PackageActions::track()` wraps a callable to emit
  its start → success|failure lifecycle automatically. See
  [`docs/tools/action-events.md`](docs/tools/action-events.md).
- **Full-fidelity migration lifecycle** — Laravel emits no migration-failure
  event, so the migrator is decorated (`FailureAwareMigrator`, console-only,
  config-gated) to emit Started/Succeeded/Failed with the real migration name
  and exception. Composition-safe: when another package has already decorated
  the migrator, a conflict-free event detector (`MigrationFailureDetector`) is
  used instead so it never clobbers them.
- **Fluent provider builders** on `Package` (see
  [`docs/tools/provider-builders.md`](docs/tools/provider-builders.md)):
  `useHttps()` / `setLocale()` / `paginator()` (with `*FromConfig` variants);
  `mergesConfigDefaults()` / `mergesConfigDefaultsFrom()` (register-phase,
  host-wins) and a failure-safe boot-time `configDecorator()`;
  `registerGate()` / `registerGates()`; `RouteGroupDefinition` +
  `registerRouteGroup(s)` (with a route-cache guard); `registerRouteModel()` /
  `registerRouteBinding()`; and an `event()` sub-builder with pair/map
  `addListeners()` and class- or closure-based `addSubscriber()`.
- Two config gates: `events.lifecycle` and `events.failures`
  (`PACKAGE_TOOLS_LIFECYCLE_EVENTS` / `PACKAGE_TOOLS_FAILURE_EVENTS`), plus
  `migrations.failure_detection` (`PACKAGE_TOOLS_MIGRATION_FAILURE_DETECTION`).

### Changed

- **Failure logging is now owned by the reporter.** The previously-inline
  `Log::error` in the seeder executor moved into `PackageActionReporter`
  (the single choke point). A failure is always logged at error; the log line
  format changed accordingly (see `UPGRADING.md`). The 8 bespoke seeder events
  are untouched — the unified family fires alongside them.
- Seeder failures on the **autorun**, **`db:seed` resolver**, and **queued
  job** paths — previously swallowed — now report through the reporter (the
  job also gained a `failed()` hook that classifies queue timeouts).

## [3.3.0] - 2026-07-09

### Changed

- **Environment-aware error handling for misconfigured schedules.** A bad
  cadence (unknown scheduler method) or a throwing `schedulesUsing()`
  callback is now wrapped in a typed `ScheduleConfigurationException`
  (with the command/bundle + cause as structured context), **always
  logged** to the package's own logfile, then handled by policy: strict
  (rethrow) outside production, lenient (skip, so one package's typo can't
  abort the whole scheduler) in production. Override via
  `package-tools.scheduling.strict` / `PACKAGE_TOOLS_SCHEDULING_STRICT`.
  Previously a raw `InvalidArgumentException` propagated and aborted the
  entire scheduler in every environment.

### Added

- `ScheduleConfigurationException` (`commandFailed` / `callbackFailed` /
  `seederFailed` factories with `$context`).
- `package-tools.scheduling.strict` config key + docs.

## [3.2.0] - 2026-07-09

### Changed

- **`AboutSectionDefinition::field()` accepts any type.** A field value
  may now be any type — `null`, backed/pure enums, `DateTimeInterface`,
  arrays, `Arrayable`, `Stringable`/`__toString` objects, or a closure
  returning any of those — not just `Closure|bool|float|int|string`.
  Each renders to a sensible display string (enums → value/name, dates →
  ISO-8601, arrays/objects → compact JSON). Fully backward compatible.

### Added

- New `docs/tools/scheduling.md` reference covering
  `registerScheduledCommand()`, `registerScheduledCommands()`, and the
  `schedulesUsing(Closure)` raw scheduler callback.
- `docs/tools/about-sections.md` gains a full field-value-types table.

## [3.1.1] - 2026-07-09

### Fixed

- **Boot no longer crashes on a malformed seeder file.** A package
  shipping a seeder source that throws during discovery (parse/require
  failure) previously propagated out of `bootPackageAutoSeeders()` and
  took down the host app on every request. The broken bundle is now
  logged (via `$package->log()`) and skipped; healthy bundles still
  register.

## [3.1.0] - 2026-07-08

### Added

- **`AboutSectionDefinition::fallback(string)`** and failure-safe field
  resolution: a field closure that throws (e.g. a query against an
  unmigrated database) now renders the section's fallback (`n/a` by
  default) instead of crashing `php artisan about`; a throwing
  whole-array `fieldsUsing()` source is skipped without affecting other
  fields. Package authors no longer need to wrap each field in `rescue()`.
- New `docs/tools/about-sections.md` reference page.

## [3.0.0] - 2026-07-08

The seeder subsystem is redesigned around one rule — **seeders never run
on their own unless you opt in** — and two new features land: per-package
logging via `$package->log()` and scheduled/background seeding with
completion events. See `UPGRADING.md` ("2.x to 3.0") for the migration.

### Added

- **`$package->log()` per-package logging**: full PSR-3 level set plus
  `success()`, each with an optional label
  (`->error('CountrySeeder failed', 'Seeder', [...])`), writing
  beautifully formatted lines (`[ts] [vendor/pkg] [LEVEL] [Label] msg |
  {json}`) to a dedicated `storage/logs/{vendor}-{package}.log`. Backed
  by a real named channel `logging.channels.{vendor}-{package}` (a
  host-defined channel of that name wins untouched); fluent
  `LogDefinition` for author defaults; host overrides at
  `{vendor}.{package}.logging.*` and `package-tools.logging.*`; early
  lines written inside `configurePackage()` buffer with original
  timestamps and flush at boot; logging never throws; container alias
  `laranail.logger.{vendor}-{package}`.
- **Autorun after migrations (opt-in)**:
  `AutoSeederDefinition::autorunAfterMigrations()` / `autorunNow()` (and
  package-level `autorunSeeders()`) run bundles once after a successful
  `php artisan migrate` (`MigrationsEnded`, nested `call('migrate')`
  included), gated by a global kill-switch, console-only,
  tests/production opt-ins, `autorunInEnvironments(...)`, a per-package
  host veto, and a shared per-process ledger (`migrate --seed` never
  double-runs; `PackageSeeder::resetRunState()` escape hatch).
- **Background execution**: `runsInBackground()`/`queued()` +
  `onQueue(BackedEnum|string, connection:)` run a bundle via
  `RunSeederBundleJob` (payload = bundle key + mode enum only); defaults
  from `package-tools.seeders.queue.*`.
- **Scheduled seeding**: `scheduledAt(TimeOfDay|string)` /
  `cadence(Cadence|CronExpressible|string|Closure)` +
  `withoutOverlapping()` put bundles on the host scheduler as
  `laranail::package-tools.seed --key=X --scheduled`.
- **`laranail::package-tools.seed` command**: explicit trigger with
  `--key`/`--package`/`--sync`/`--queued`/`--force`, and `--status`
  rendering the new cache-backed `SeederRunTracker` progress store.
- **Completion events**: `PackageSeedingStarted` /
  `PackageSeedingCompleted` / `PackageSeedingFailed` fire for every
  execution mode (host attaches its own Notification/mail/broadcast
  listener); suppress per bundle via `notifiesOnCompletion(false)` or
  globally via `package-tools.seeders.events.enabled`. Outcomes also
  land in the owning package's own logfile.
- **New enums**: `QueueConnection`, `SeederExecutionMode`,
  `SeederRunStatus` — every new fluent parameter takes
  `BackedEnum|string`, so host apps can pass their own enums.
- **Definition additions**: `addSeeders()`, `stopOnFailure()`,
  `discoverIn($path, recursive: true)`,
  `InstallCommandDefinition::runsSeeders()`.
- **Shipped config** `config/package-tools.php` (`logging.*`,
  `seeders.root_seeders`, `seeders.autorun.*`, `seeders.queue.*`,
  `seeders.events.*`), publishable via tag `package-tools-config`.

### Fixed

- **`loadSeedersFrom()` / `registerSeeder()` actually work** — in 2.x
  they stored state nothing consumed (silent no-ops); they now feed the
  definition pipeline.
- **Executed seeders receive the container** — `run()`-signature
  dependency injection and `$this->call()` no longer fatal
  (`SeederExecutor` now matches Laravel's own `db:seed` behavior).
- **db:seed failures are visible** — the resolver hook reports failures
  to the console instead of exiting 0 silently.
- **Late registrations aren't lost** — the hook fires per root-seeder
  resolution against the live registry (the 2.x one-shot flag dropped
  bundles registered after the first firing).
- **Discovery**: abstract seeders are excluded; non-autoloaded files are
  `require`d on demand (honoring the documented contract); same-namespace
  discovery keys no longer clobber each other (path hash in the key).
- **Registry replacement warns** instead of silently clobbering.
- **`options(['priority' => …])` is honored** — a never-set fluent
  `priority()` no longer overwrites it with the default 0.
- **`SeederException::executionFailed()`** wraps execution failures
  (was dead code).

### Changed (BREAKING)

- Package bundles no longer run when an **arbitrary** seeder resolves —
  only on root-seeder resolution (`Database\Seeders\DatabaseSeeder` +
  `package-tools.seeders.root_seeders`), opt-in autorun, the schedule,
  or explicit runs. `db:seed --class=X` no longer triggers package
  bundles.
- `Package::getSeederPaths()` / `getRegisteredSeeders()` removed — use
  `getPackageSeederDefinitions()`.
- `SeederResolverHook::attach()` takes variadic root-seeder FQCNs and
  requires the shared `SeederAutorun` collaborator; the hook is attached
  once by `PackageToolsServiceProvider` (no longer lazily by
  `autoSeed()`).
- `SeederManager` constructor drops `Application` and gains
  `SeederAutorun`; `SeederManager::run()` marks the executed-key ledger.
- `SeederExecutor::run()` gained an optional `SeederExecutionMode`
  parameter and now emits bundle-level events/tracking.

## [2.3.1] - 2026-07-06

### Fixed

- **Middleware group boot no longer replaces host groups**: booting an
  `addToMiddlewareGroup('web', ...)` registration was overwriting the
  host's entire `web` group (sessions, CSRF) with the package's list; it
  now appends via `pushMiddlewareToGroup()` and only defines groups the
  router does not have.
- **CronBuilder validation tightened**: zero steps (`*/0`), inverted
  ranges (`5-2`), field-minimum violations (`dayOfMonth('0')`), and
  `*-N` forms are rejected; `everyHours()` no longer clobbers an
  explicitly set minute.
- **Deferred-call argument normalization is recursive**: enum/TimeOfDay/
  CronExpressible values inside array arguments (e.g.
  `environments([Environment::Production])`) now normalize like scalars.
- **Config cadences apply once** across repeated Schedule resolutions
  (tests, Octane) instead of re-recording duplicate frequency calls.
- **Raw-cron detection** no longer mistakes any five words for an
  expression; only field-shaped tokens route to `cron()`.
- **Seeder definitions**: duplicate explicit seeders resolve once;
  discovery over a missing directory registers nothing instead of
  throwing at boot.
- **About sections** accept numeric-string field keys.
- The unified doctor command's table and `--json` output surface the
  registering package (`group`).
- Documentation accuracy pass across every page (FQCNs, signatures,
  stale 1.x architecture claims, dead example links, missing 1.3/2.x
  service references).

## [2.3.0] - 2026-07-06

### Added

- **Fluent install commands**: `hasInstallCommand()` accepts an
  `InstallCommandDefinition` — named steps executed in declaration order
  (built-ins and custom `step()`s interleave freely, replacing the fixed
  pipeline), `publishes()`/`runsMigrations()`/`asksToRunMigrations()`/
  `copiesServiceProvider()`/`asksToStarRepo()` built-ins, `named()` and
  `visible()` overrides, and the standard toArray/toJson surface.
  Definition commands are built lazily and console-only — nothing is
  constructed on web requests (the legacy callable form constructed the
  command eagerly at configure time, and still works unchanged).
- `InstallCommand::getPackage()`, `starRepoNow()`, `copyProviderNow()`
  public step triggers; constructor accepts optional signature/hidden
  overrides.

### Fixed

- **Install publishing was silently a no-op for namespaced packages**:
  both the definition and legacy paths now try the namespaced publish tag
  (`vendor::pkg-{tag}`) as well as the legacy `{short-name}-{tag}` form.

## [2.2.0] - 2026-07-06

### Added

- **Fluent doctor checks**: `DoctorCheckDefinition` — static factories for
  the whole bundled check library (`phpVersion`, `phpExtensions`,
  `configPresent`, `writablePaths`, `reachable`, `softDependency`,
  `callback`, and `wrap()` for custom checks), chainable
  `named()`/`describe()` overrides, `whenConfig`/`whenConfigNotNull`
  gating via the shared ConfigGate (a failed gate means the check is never
  registered), and the standard toArray/toJson surface. The definition IS
  a DoctorCheck, so everything existing keeps working.
- **Package attribution**: `DoctorService::register()` takes an optional
  `$group` and report rows carry it (surfaced in the `DoctorReporter`
  table/JSON and `HealthResponder` shapes); the provider's boot step
  passes the registering package's name automatically.
- `DoctorResult` implements `Arrayable`.
- `WritablePathCheck` accepts a plain list of paths (paths label
  themselves) alongside the label => path map.

### Fixed

- `DoctorService::register()` no longer stacks duplicate report rows when
  the same (group, name) pair registers twice (double boots were silently
  duplicating checks); the later registration replaces the earlier one.

## [2.1.0] - 2026-07-06

### Added

- **Fluent about sections**: `hasAboutSection()` accepts an
  `AboutSectionDefinition` — per-field lazy closures (no mega-closure over
  everything), `fieldsUsing()` whole-array sources with explicit-field
  precedence, `whenConfig`/`whenConfigNotNull` gating via the shared
  ConfigGate, and the standard toArray/toJson surface. The legacy
  label + callable form is unchanged.

## [2.0.0] - 2026-07-06

### Added

- **Fluent scheduler registration**: `registerScheduledCommand(s)` with
  `ScheduledCommandDefinition` — a two-tier fluent surface where the
  cron-expressible frequency vocabulary lives once on the reusable
  `CronBuilder` and everything else (sub-minute frequencies, runtime
  constraints, execution modifiers) defers to the real scheduler event;
  `cadence()`, `cron()`, `cadenceFromConfig($key, $default)` (missing key
  falls back, explicit null opts out, raw 5-field cron values accepted),
  `configure()` and `schedulesUsing()` escape hatches.
- **`CronBuilder`** (`Support\Scheduling`): validated, standalone cron
  designer behind the new `CronExpressible` contract — field setters,
  steps/lists/ranges, `at()`, `daily/weekly/monthly/quarterly/yearly`,
  `twiceWeekly/twiceMonthly` (+ `biWeekly`/`biMonthly` sugar),
  `weekdays/weekends`, `fromExpression()`.
- **`TimeOfDay`** (`Support\Scheduling`): 24h/12h parsing, `am()/pm()`
  constructors, minute arithmetic with midnight wrap, `format24()/format12()`.
- **Enums**: `Cadence` (every scheduler frequency; config strings resolve
  through it first), `Weekday` (sunday = 0), `Environment`, and a
  GENERATED `Timezone` enum covering every iana identifier
  (`tools/generate-timezone-enum.php`).
- **`ConfigGate`** (`Support`): the single config-gating implementation
  behind every `whenConfig()` / `whenConfigNotNull()` (truthy and
  configured-means-on modes).
- **`DeferredCallQueue`** (`Support`): generic capture/replay with one
  argument normalizer (BackedEnum → value, TimeOfDay → 'H:i',
  CronExpressible → expression).
- **Authorization**: `registerPolicy`/`registerPolicies` (`Gate::policy`
  at boot).
- **Morph maps**: `registerMorphMap(array|Closure)` and
  `registerMorphMapFromConfig($mapKey, $userModelKey)` with user-model
  fallback chain, subclass validation, non-enforcing registration.
- **Rate limiters**: `registerRateLimiter(s)`.
- **Observers**: `registerObserver(s)`.
- **Conditional routes**: `hasRoutesWhen($configKey, $files, $default)`.
- **Blade**: `hasBladeComponentNamespace()`, `hasBladeComponentAlias(es)`.
- **Livewire**: config gate on `hasLivewireComponents(..., whenConfig:)`,
  `withoutLivewireNamespacePrefix()`, and reactive registration — when
  livewire's provider registers late (dont-discover setups) the components
  register the moment it binds.
- **Middleware**: batch `registerRouteMiddlewares()`.
- **Seeders**: `hasPackageSeeders` accepts a fluent `AutoSeederDefinition`
  (explicit list or discovery mode, `ignoreSeeders()` exclusion,
  `whenConfig` gates, `priority`); `SeederBundle` typed options.

### Changed (BREAKING)

- **Seeder execution model unified**: all package seeders run at `db:seed`
  time via the resolver hook; the boot-time-immediate execution path
  (`bootPackageSeeders()`) is removed — seeders never run at app boot.
- **Middleware registration uniformly deferred**: the enhanced methods
  (aliases, groups, prefixed) now store on the package and apply in
  `bootPackageMiddleware()` instead of writing to the router at configure
  time.
- **Seeder registry entries are typed `SeederBundle` VOs** (`get()`/`all()`
  no longer return shape-arrays); bundles execute in priority order
  (lower first, stable ties).
- New deferred-hook boot order: morph maps boot first (models may be
  touched by any later step), then middleware/events/policies/observers/
  rate limiters/factories/seeder registration.

### Fixed

- `hasComponentNamespace()` registrations were never applied at boot
  (write-only array) — they now register through `Blade::componentNamespace()`.
- Seeder options (`disable_foreign_key_checks`, `fire_events`,
  `parameters`) no longer bleed across packages: scoped per bundle.
- Stale composer branch alias (`dev-main` now `2.x-dev`).

## [1.3.0] - 2026-06-27

### Added

- **Reusable doctor check library** (`Services/Doctor/Checks/`): `PhpExtensionCheck`,
  `PhpVersionCheck`, `WritablePathCheck` (with optional free-disk warning), `ConfigPresentCheck`,
  `SoftDependencyCheck`, `ReachabilityCheck` (a failing/throwing probe is a WARN, never a FAIL),
  and a `CallbackCheck` escape hatch — so consuming packages compose a doctor from parameterised
  instances instead of bespoke classes.
- **`DoctorReporter`** (table/JSON render + summary + exit code) and **`HealthResponder`**
  (`200 healthy` / `503 degraded` JSON, now with a `summary` block) collapse the per-package
  doctor-command and health-controller boilerplate to one line each. Adds `illuminate/console`
  + `illuminate/http` to `require`.
- **`PackageServiceProvider::bootPackageDoctorChecks()`** wires a package's declared
  `->hasDoctorChecks()` into the shared `DoctorService` at boot, so they surface in the unified
  `laranail::package-tools.doctor` command.
- **`Package::hasTranslations(?string $alias)`** registers an optional short translation namespace
  alongside the full `vendor/package` one (e.g. `license-kit::` as well as `laranail/license-kit::`).
- **`Package::withoutConfigNamespacing()`** merges a flat config file under its bare file name
  (`config('file.*')`) instead of the `vendor.package` key — for packages that read bare config keys.

### Changed

- `Package::hasDoctorChecks()` now accepts any `iterable` (was `array`).
- `DoctorReporter` JSON `status` uses the `healthy`/`degraded` vocabulary (was `ok`/`degraded`),
  matching `HealthResponder`.

## [1.2.1] - 2026-06-26

### Fixed (docs)

- Document the v1.2.0 builder surface that was added after the `v1.2.0` tag:
  `registerMiddlewareAliases` (the canonical middleware batch entry),
  `registerMiddlewareGroups`/`addToMiddlewareGroup`/`registerPrefixedMiddleware`,
  `loadAllResources()`, and the README "Status" line (now v1.2.0).
- `docs/installation.md`: corrected the `symfony/process` constraint to `^8.0`
  (matches `composer.json`).
- `docs/architecture.md` + the `Package` class comment: the builder has **14**
  domain aggregator traits (added `ConfiguresEvents`), and **all 46 leaf traits
  are wired** — removed the stale "six leaves stay unwired" note.

## [1.2.0] - 2026-06-26

### Added

- **`Package::publishFile()` / `Package::publishDirectory()`** — publish a single file
  or a directory under the package's namespaced publish tag (`vendor::package-{suffix}`),
  with the suffix defaulting to the source's name. Replaces the verbose
  `->publish([$src => $dest], $package->getNamespacedPublishTag($suffix))` pattern.
- **`Testing\AssertsPublishedConfigOverrides`** trait — reliably test that a published
  namespaced-config override reaches its dotted key (writes the file, registers a fresh
  provider instance so the register-phase bridge picks it up, asserts, and cleans up).
  Mixed into `Testing\IsolatedTestCase` so it is available out of the box.
- **Batch fluent helpers** so consumers pass one array instead of chaining repeated
  calls: `hasValidationRules()`, `hasAboutSections()`, `hasDoctorChecks()`,
  `registerNamespacedConfigs()`, and `sharesDataWithAllViews()` now also accepts an
  associative `name => value` array.

### Notes

- `registerMiddlewareAliases(['alias' => Class::class])` is the canonical batch entry
  point for route-middleware aliases — no separate `registerRouteMiddlewares` is added
  (it would duplicate it; both resolve to `$router->aliasMiddleware()`).

## [1.1.0] - 2026-06-26

### Added

- **Publishable namespaced config.** A namespaced config (`config('vendor.package.*')`)
  publishes to a nested path (`config/vendor/package.php`) that Laravel does not
  auto-load, so a published override was previously ignored. `registerPackageConfigs()`
  now loads the published override and recursively merges it over the vendor defaults
  (cache-safe), so `vendor:publish` + edit actually overrides the dotted key.
- **`Package::hasChildProviders(array $providers)`** — register child service providers
  (eager or deferred) at register time.
- **`Package::hasValidationRule(string $name, string $ruleClass, ?string $message = null)`**
  — register a custom validator rule backed by a Laravel `ValidationRule` class.
- **`Package::hasAboutSection(string $label, callable $data)`** — add a section to
  `php artisan about` (guarded when the About command is unavailable).

### Fixed

- **`getNamespacedConfigKey()` collision.** It returned `vendor.package` for *every*
  config file, so a package with multiple config files merged them all into one key.
  Additional files now get a per-file sub-key (`vendor.package.{file}`); the default
  file (== package short-name) still maps to the bare `vendor.package`.

## [1.0.0] - 2026-06-26

Initial stable release — a runtime base library for building Laravel packages: the
fluent `Package` builder + abstract `PackageServiceProvider`, namespaced/nested
config, attribute discovery, a seeder subsystem, and the `package-tools.doctor` /
`.sbom` / `.audit` / `.ide-helper` commands. Requires `laranail/console ^1.0`.
