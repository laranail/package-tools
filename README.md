# laranail/package-tools

> Runtime base library for building Laravel packages.

A fluent `Package` builder + abstract `PackageServiceProvider` that
consumers extend. Adds attribute-driven discovery, `package:doctor`, an
isolation testing harness, and an append-only Laravel `.env` writer on
top of the `spatie/laravel-package-tools` approach.

## Status

**v1.0 pre-release.** Phases 4 + 5A of the laranail suite cleanup landed
the v1.0 surface (May 2026). 124 src files, 35 tests, 12 domain
aggregator traits wiring 25 active leaf traits (ADR-004), plus the four
v1.0 differentiators.

## Targets

- PHP `^8.3 || ^8.4`
- Laravel `^13.0`
- Pest `^3.0`, Testbench `^11.0`, Larastan `^3.0`

## Install

```bash
composer require laranail/package-tools
```

## Quick example

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
            ->hasInstallCommand(fn ($cmd) => $cmd->publishConfigFile()->askToRunMigrations())

            // v1.0 differentiators:
            ->discoversWithAttributes()              // ADR-009 — scan src/ for #[AsArtisanCommand], #[AsRoute], #[AsViewComposer]
            ->hasDoctorCheck(MyHealthCheck::class);  // wires into php artisan package:doctor
    }
}
```

## v1.0 surface

### Fluent builder (Spatie parity)

`name()`, `hasConfigFile()`, `hasViews()`, `hasViewComponents()`,
`hasInertiaComponents()`, `hasViewComposer()`, `sharesDataWithAllViews()`,
`hasTranslations()`, `hasAssets()`, `hasRoute()`, `hasMigration()`,
`runsMigrations()`, `discoversMigrations()`, `hasCommand()`,
`hasInstallCommand()`, `publishesServiceProvider()`.

### v1.0 extensions (Spatie differentiators)

| Step / class | What it does |
|---|---|
| `Package::discoversWithAttributes()` | Scans `src/` for classes carrying `#[AsArtisanCommand]`, `#[AsRoute]`, `#[AsViewComposer]` and wires them automatically. ADR-009. |
| `Package::hasDoctorCheck(MyCheck::class)` | Registers a `DoctorCheck` for `php artisan package:doctor`. |
| `Testing\IsolatedTestCase` | Opinionated Testbench wrapper — in-memory SQLite, sync queue, `assertTableExists`/`assertCommandExists`/`createTempPath` helpers. |
| `Services\Environment\EnvFileService` | Append-only writer for the host Laravel app's `.env`. Atomic, backup-first, never destructive. ADR-006. |

### Library Artisan commands

Auto-registered via `extra.laravel.providers`; available the moment the
package is installed. Full reference in [`docs/COMMANDS.md`](docs/COMMANDS.md).

| Command | What it does |
|---|---|
| `php artisan package:doctor` | Runs every registered `DoctorCheck`; exits non-zero on any `FAIL`. `--json` / `--strict`. |
| `php artisan package:sbom` | Emits a CycloneDX 1.5 JSON SBOM for `composer.lock`. `--output=` / `--print`. |
| `php artisan package:audit` | Posts `composer.lock` to OSV.dev; exits non-zero when advisories returned. `--no-dev` / `--json` / `--timeout=`. |
| `php artisan package:ide-helper` | Generates Facade classes from `#[AsFacade]` contracts with `@method` docblocks. |

### Discovery attributes

```php
use Simtabi\Laranail\PackageTools\Attributes\AsArtisanCommand;
use Simtabi\Laranail\PackageTools\Attributes\AsRoute;
use Simtabi\Laranail\PackageTools\Attributes\AsViewComposer;
use Simtabi\Laranail\PackageTools\Attributes\AsFacade;

#[AsArtisanCommand(signature: 'foo:run', description: 'Run the foo task')]
class FooCommand extends Command { … }

#[AsRoute(method: 'GET', uri: '/foo')]
#[AsRoute(method: 'POST', uri: '/foo', name: 'foo.create', middleware: ['web'])]
class FooController { … }

#[AsViewComposer(views: ['layouts.app', 'partials.header'])]
class AppViewComposer { … }
```

## Local development

```bash
bash scripts/init.sh        # one-shot bootstrap (verifies php >= 8.3, runs composer install, smoke-checks)
composer setup              # alias for scripts/init.sh
composer test               # vendor/bin/pest --colors=always
composer test-coverage
composer lint               # pint + phpstan + rector --dry-run
composer pint-fix           # apply Pint formatting
composer rector-fix         # apply Rector transformations
composer audit              # composer audit (security advisories)
```

`scripts/init.sh` is the only shell script in the repo (per ADR-007 of
the suite plan — tooling is pure PHP/Composer).

## Provenance

This package's design draws on [`spatie/laravel-package-tools`](https://github.com/spatie/laravel-package-tools)
(MIT) — the open API surface (`hasConfigFile()`, `hasViews()`, lifecycle
hooks, etc.) is intentionally compatible. Code is copyright Simtabi (see
[LICENSE.md](LICENSE.md)).

## Roadmap

- **v1.0** ✅ — attribute discovery, `package:doctor`, `IsolatedTestCase`, `EnvFileService`.
- **v1.1** ✅ — `package:sbom` (CycloneDX 1.5), `package:audit` (OSV.dev).
- **v1.2** ✅ — `package:ide-helper` + `FacadeAutoGenerator` from `#[AsFacade]`.
- **Next** — first `v1.0.0-beta.1` tag dry-run; consolidate the six unwired
  collision-by-design traits documented in [ADR-0011](docs/adr/0011-deferred-trait-wiring.md).

## Documentation

- Primary: [`opensource.simtabi.com/package-tools/docs/`](https://opensource.simtabi.com/package-tools/docs/)
- Portal: [`opensource.simtabi.com/package-tools/`](https://opensource.simtabi.com/package-tools/)
- In-repo: [`docs/`](docs/) — ARCHITECTURE, SERVICES, CONFIGURATION, COMMANDS
- Changelog: [CHANGELOG.md](CHANGELOG.md)

## Sister packages

- [`laranail/package-scaffolder`](https://github.com/laranail/package-scaffolder) — Artisan generator that scaffolds new packages built on top of `package-tools`.
- [`laranail/database-tools`](https://github.com/laranail/database-tools) — independent Laravel DB utilities; usable by any Laravel app, no `package-tools` dependency.
- [`laranail/laranail`](https://github.com/laranail/laranail) — Simtabi's Laravel utility toolbox.

## License

MIT. See [LICENSE.md](LICENSE.md).
