# Changelog

All notable changes to `laranail/package-tools` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- **`Services\Config\ConfigService::forget()` could not remove a top-level
  key.** It pruned a copy of `all()` and re-seeded the survivors — but a removed
  top-level key is simply absent from that copy, so nothing ever touched it and
  `get()` kept returning the old value. Nested keys worked, which is why it went
  unnoticed.

- **Booting a package no longer deletes published assets.**
  `PackageServiceProvider::bootPackageCustomPublishes()` and
  `ProcessAssets::bootPackageAssetRegistry()` deleted the destination of every
  publish tag marked `cleanBeforePublish` / `clean: true`. The only guard was
  `runningInConsole()`, and every console command boots every provider — so
  `php artisan route:list` removed the published assets of any package that had
  asked for a clean, and they did not come back until someone re-published.

  Boot now records the request rather than acting on it. See
  [UPGRADING.md](UPGRADING.md#boot-no-longer-deletes-published-assets) for what
  changes for a package that used the flag.

### Added

- **`Services\Config\ConfigManager`** (+ `Contracts\ConfigManagerInterface`) —
  a fluent, chainable runtime configuration manager, relocated here from
  `laranail/toolkit`. Config machinery belongs with the package-authoring
  runtime, which already owns the resolver, merger, validator and pattern
  resolver under `Services/Config/`.

  It sits alongside `ConfigService` rather than replacing it, and the boundary is
  now documented: `ConfigService` is boot-time `mergeConfigFrom` semantics where
  app config wins, `ConfigManager` is runtime override where the caller wins.
  Bound with `bind()`, not `singleton()` — it carries a base path and an
  operation log, so two callers configuring two module roots must not share one.

  Two things changed on the way over. `remove()` now makes both `get()` and
  `has()` miss for a top-level key, where before it could only null the value —
  the pruned array replaces the repository's item store via
  `Services\Config\ConfigItemStore`, degrading to nulling against a custom
  `Repository`. And `dump()` / `dd()` were added.

  See [docs/tools/config-manager.md](docs/tools/config-manager.md).

- **`Services\Config\ConfigItemStore`** — the one place that knows how to remove
  a config key, so `ConfigManager::remove()` and `ConfigService::forget()` cannot
  drift into disagreeing about what "forget" means.

- **`Services\Asset\PublishTagRegistry`** (+ `PublishTagEntry`) — a singleton
  recording every publish tag a laranail package registers, which package owns
  it, and whether it asked for its destination to be cleaned first. Laravel's own
  `ServiceProvider::$publishGroups` records tag => paths but knows nothing about
  ownership or cleaning. Repeat records for one tag merge their paths, and
  `cleanable` is sticky — one call site asking for a clean is enough.

## [0.1.0] - 2026-07-11

Initial public release.
