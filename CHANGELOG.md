# Changelog

All notable changes to `laranail/package-tools` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
