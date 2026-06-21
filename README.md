# laranail/package-tools

[![Latest version on Packagist](https://img.shields.io/packagist/v/laranail/package-tools.svg)](https://packagist.org/packages/laranail/package-tools)
[![Tests](https://github.com/laranail/package-tools/actions/workflows/tests.yml/badge.svg)](https://github.com/laranail/package-tools/actions/workflows/tests.yml)
[![Static analysis](https://github.com/laranail/package-tools/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/laranail/package-tools/actions/workflows/static-analysis.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

> Runtime base library for building Laravel packages.

A fluent `Package` builder and an abstract `PackageServiceProvider` your
package extends, in the spirit of `spatie/laravel-package-tools`. On top of
that base it adds attribute-driven discovery, a set of `package-tools.*`
Artisan commands, abstract HTTP controllers, and a testing harness.

**Status:** v0.2.0 — adds the database seeding subsystem.

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [What you get](#what-you-get)
- [Artisan commands](#artisan-commands)
- [Attribute discovery](#attribute-discovery)
- [Documentation](#documentation)
- [Local development](#local-development)
- [Provenance](#provenance)
- [Sister packages](#sister-packages)
- [Contributing & security](#contributing--security)
- [License](#license)

## Requirements

- PHP `^8.4` (8.4 and 8.5 are supported; CI tests the full `8.4 / 8.5` matrix). The floor is 8.4 because `laranail/console` (a dependency) requires PHP `^8.4.1`.
- Laravel `^13.0`
- For development: Pest `^3.0`, Testbench `^11.0`, Larastan `^3.0`

## Installation

```bash
composer require laranail/package-tools
```

The service provider is auto-discovered, so the `package-tools.*` commands are
available as soon as the package is installed.

## Quick start

Extend `PackageServiceProvider` and describe your package in
`configurePackage()`:

```php
use Simtabi\Laranail\PackageTools\Package;
use Simtabi\Laranail\PackageTools\PackageServiceProvider;

final class FooServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('vendor/foo')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_foos_table')
            ->hasInstallCommand(fn ($command) => $command
                ->publishConfigFile()
                ->askToRunMigrations())
            ->discoversWithAttributes()
            ->hasDoctorCheck(FooHealthCheck::class);
    }
}
```

A complete, runnable example lives in
[`docs/examples/`](docs/examples/HelloPackageServiceProvider.php).

## What you get

| Capability | Summary |
|---|---|
| Fluent `Package` builder | Spatie-compatible surface: `name()`, `hasConfigFile()`, `hasViews()`, `hasViewComponents()`, `hasInertiaComponents()`, `hasViewComposer()`, `sharesDataWithAllViews()`, `hasTranslations()`, `hasAssets()`, `hasRoute()`, `hasMigration()`, `runsMigrations()`, `discoversMigrations()`, `hasCommand()`, `hasInstallCommand()`, `publishesServiceProvider()`. |
| Attribute discovery | `discoversWithAttributes()` scans `src/` for `#[AsArtisanCommand]`, `#[AsRoute]`, and `#[AsViewComposer]` and registers them for you. |
| Namespaced config | `hasNestedConfig()` / `discoversConfig()` mount config sub-folders at dotted keys (`config/admin/panel.php` → `config('admin.panel.*')`), with an optional in-file `__namespace`. See [config-namespacing](docs/tools/config-namespacing.md). |
| Artisan commands | `laranail::package-tools.doctor`, `.sbom`, `.audit`, `.ide-helper` (see below). |
| HTTP controllers | Optional `WebController` / `ApiController` base classes for package routes. |
| Testing harness | `Testing\IsolatedTestCase` — Testbench wrapper with in-memory SQLite, sync queue, and schema/command assertions. |
| Runtime services | `SystemService`, `HttpConfigurationService`, and `ErrorStorageService`, bound by the provider and resolvable from the container. |

The full reference is in [`docs/`](docs/) — see [Documentation](#documentation).

## Artisan commands

Registered automatically via package auto-discovery.

| Command | Purpose | Options |
|---|---|---|
| `php artisan laranail::package-tools.doctor` | Run every registered `DoctorCheck`; non-zero exit on any failure. | `--json`, `--strict` |
| `php artisan laranail::package-tools.sbom` | Emit a CycloneDX 1.5 JSON SBOM for `composer.lock`. | `--output=`, `--print` |
| `php artisan laranail::package-tools.audit` | Query OSV.dev for advisories in `composer.lock`; non-zero exit on any advisory. | `--no-dev`, `--json`, `--timeout=` |
| `php artisan laranail::package-tools.ide-helper` | Generate Facade classes from `#[AsFacade]` contracts with `@method` docblocks. | `--source=`, `--output=` |

Commands follow the `laranail::<package-slug>.<command>` naming used across the
laranail family (the `::` separator is enabled by the package's base command —
see [CONTRIBUTING.md](CONTRIBUTING.md#artisan-command-naming)).

## Attribute discovery

```php
use Simtabi\Laranail\PackageTools\Attributes\AsArtisanCommand;
use Simtabi\Laranail\PackageTools\Attributes\AsRoute;
use Simtabi\Laranail\PackageTools\Attributes\AsViewComposer;
use Simtabi\Laranail\PackageTools\Attributes\AsFacade;

#[AsArtisanCommand(signature: 'foo:run', description: 'Run the foo task')]
class FooCommand extends Command {}

#[AsRoute(method: 'GET', uri: '/foo')]
#[AsRoute(method: 'POST', uri: '/foo', name: 'foo.create', middleware: ['web'])]
class FooController {}

#[AsViewComposer(views: ['layouts.app', 'partials.header'])]
class AppViewComposer {}
```

See [`docs/tools/attribute-discovery.md`](docs/tools/attribute-discovery.md).

## Documentation

Hosted at [`opensource.simtabi.com/package-tools/docs/`](https://opensource.simtabi.com/package-tools/docs/)
(product page: [`opensource.simtabi.com/package-tools/`](https://opensource.simtabi.com/package-tools/)).
The same pages live under [`docs/`](docs/):

**Guides**

- [Installation](docs/installation.md) — requirements, install, auto-registration
- [Configuration](docs/configuration.md) — the fluent `Package` builder and lifecycle hooks
- [Seeding](docs/seeding.md) — package + standalone seeding: registry, executor, fluent builder, `SeederExecutionStats`, events, console output
- [Architecture](docs/architecture.md) — how the runtime is put together
- [Services reference](docs/services.md) — the service and support classes

**Tools & features**

- [package-tools.doctor](docs/tools/doctor.md) — health checks
- [package-tools.sbom](docs/tools/sbom.md) — CycloneDX SBOM
- [package-tools.audit](docs/tools/audit.md) — OSV.dev advisory scan
- [package-tools.ide-helper](docs/tools/ide-helper.md) — Facade generation from `#[AsFacade]`
- [Attribute discovery](docs/tools/attribute-discovery.md) — `#[AsArtisanCommand]`, `#[AsRoute]`, `#[AsViewComposer]`
- [Namespaced config](docs/tools/config-namespacing.md) — folder-tree config keys (`config('admin.panel.*')`) + the optional `__namespace`
- [Command naming](docs/tools/command-naming.md) — the `laranail::<slug>.<command>` separator, the base `Command`, and `SupportsNamespacedNames`
- [HTTP controllers](docs/tools/http-controllers.md) — `WebController` and `ApiController`
- [IsolatedTestCase](docs/tools/isolated-testcase.md) — the testing harness
- [Runtime services](docs/tools/runtime-services.md) — `SystemService`, `HttpConfigurationService`, `ErrorStorageService`

**Examples**

- [Runnable examples](docs/examples/) — a cohesive `Acme\Hello` package that demonstrates every feature end to end: the fluent builder and lifecycle hooks, all four discovery attributes, namespaced commands, the `WebController`/`ApiController` bases, doctor checks, the runtime services and consumer traits, package seeders, the install-command flow, and an `IsolatedTestCase` test

## Local development

```bash
bash .scripts/init.sh   # verify PHP >= 8.4, run composer install, smoke-check
composer setup          # alias for .scripts/init.sh
composer test           # run the Pest suite
composer test-coverage  # run with coverage
composer lint           # Pint + PHPStan + Rector (dry run)
composer pint-fix       # apply Pint formatting
composer rector-fix     # apply Rector transformations
composer audit          # composer security audit
```

`.scripts/init.sh` is the only shell script in the repo; everything else runs
through Composer.

## Provenance

The open API surface (`hasConfigFile()`, `hasViews()`, the lifecycle hooks,
and friends) is intentionally compatible with
[`spatie/laravel-package-tools`](https://github.com/spatie/laravel-package-tools)
(MIT). Code is copyright Simtabi LLC; see [LICENSE](LICENSE).

## Sister packages

- [`laranail/package-scaffolder`](https://github.com/laranail/package-scaffolder) — Artisan generator that scaffolds new packages on top of `package-tools`.
- [`laranail/database-tools`](https://github.com/laranail/database-tools) — standalone Laravel database utilities; no dependency on `package-tools`.
- [`laranail/laranail`](https://github.com/laranail/laranail) — Simtabi's Laravel utility toolbox.

## Contributing & security

- [CONTRIBUTING.md](CONTRIBUTING.md) — workflow, coding standards, command naming.
- [SECURITY.md](SECURITY.md) — how to report a vulnerability.
- [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md) — community expectations.
- [CHANGELOG.md](CHANGELOG.md) — release history.

## License

MIT. See [LICENSE](LICENSE).
