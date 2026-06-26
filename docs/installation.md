# Installation

## Requirements

| Requirement | Constraint |
|---|---|
| PHP | `^8.4.1` (8.4, 8.5 supported; the `.1` floor is from `laranail/console`) |
| Laravel | `^13.0` (`illuminate/contracts`, `illuminate/support`, `illuminate/filesystem`) |
| Other runtime | `symfony/process` `^8.0` |

## Install

```bash
composer require laranail/package-tools
```

## Auto-registration

The package's `composer.json` declares its provider under
`extra.laravel.providers`:

```json
"extra": {
    "laravel": {
        "providers": [
            "Simtabi\\Laranail\\Package\\Tools\\Providers\\PackageToolsServiceProvider"
        ]
    }
}
```

Laravel package discovery registers
`Simtabi\Laranail\Package\Tools\Providers\PackageToolsServiceProvider`
automatically. No manual wiring is needed. On registration the provider:

- registers the `DoctorService` singleton,
- binds `SystemServiceInterface` (request-scoped), `ErrorStorageServiceInterface`
  (per-resolution), and the `HttpConfigurationServiceInterface` singleton,
- and, when running in the console, registers the four `laranail::package-tools.*` commands:
  `laranail::package-tools.doctor`, `laranail::package-tools.sbom`, `laranail::package-tools.audit`, `laranail::package-tools.ide-helper`.

The four commands are available the moment the package is installed.
`laranail::package-tools.doctor` only reports checks a consumer registers via
`$package->hasDoctorCheck(...)`; the other three act on the host project
directly.

## Building your own package on top of it

`laranail/package-tools` is a runtime base library; it publishes no
`config/*.php` of its own. A consumer builds a package by extending the
abstract `PackageServiceProvider` and describing the package fluently
inside `configurePackage(Package $package)`:

```php
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\PackageServiceProvider;

final class FooServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('vendor/foo')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_foos_table')
            ->discoversWithAttributes()
            ->hasDoctorCheck(MyHealthCheck::class);
    }
}
```

The full fluent surface, the provider lifecycle hooks, and the runtime
environment variables are documented in
[configuration.md](configuration.md). See
[`examples/`](examples/) for runnable examples.

## See also

- [configuration.md](configuration.md) — the fluent `Package` builder and
  lifecycle hooks.
- [architecture.md](architecture.md) — how the runtime is structured.
- [tools/runtime-services.md](tools/runtime-services.md) — the three
  bindings registered on installation, with usage.

[← Docs index](../README.md#documentation)
