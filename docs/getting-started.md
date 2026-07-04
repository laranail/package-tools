# Getting started

Build your first Laravel package on `laranail/package-tools` — a service provider + a config file in a few
minutes. For the full reference see the [Documentation index](../README.md#documentation).

## 1. Install

```bash
composer require laranail/package-tools
```

## 2. Extend the base provider

Your package's service provider extends `PackageServiceProvider` and declares itself with the fluent
`Package` builder — no manual `register()`/`boot()` wiring:

```php
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\Package\Tools\Package;

final class AcmeBlogServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('acme/blog')          // config resolves under config('acme.blog.*')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigrations()
            ->hasCommands([/* … */]);
    }
}
```

## 3. Verify

```bash
php artisan about                       # your package appears in the About section
php artisan laranail::package-tools.doctor   # health-check the package wiring
```

## Next steps

- [Configuration](configuration.md) — the full fluent `Package` builder + lifecycle hooks.
- [Attribute discovery](tools/attribute-discovery.md) — `#[AsArtisanCommand]`, `#[AsRoute]`, `#[AsViewComposer]`.
- [Command naming](tools/command-naming.md) — the `laranail::<slug>.<command>` base.
- [IsolatedTestCase](tools/isolated-testcase.md) — the package testing harness.

---

[← Docs index](../README.md#documentation)
