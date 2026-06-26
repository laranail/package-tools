# Changelog

All notable changes to `laranail/package-tools` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
