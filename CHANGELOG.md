# Changelog

All notable changes to `laranail/package-tools` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

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

- **`Services\Asset\PublishTagRegistry`** (+ `PublishTagEntry`) — a singleton
  recording every publish tag a laranail package registers, which package owns
  it, and whether it asked for its destination to be cleaned first. Laravel's own
  `ServiceProvider::$publishGroups` records tag => paths but knows nothing about
  ownership or cleaning. Repeat records for one tag merge their paths, and
  `cleanable` is sticky — one call site asking for a clean is enough.

## [0.1.0] - 2026-07-11

Initial public release.
