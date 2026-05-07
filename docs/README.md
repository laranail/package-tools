# laranail/package-tools — documentation

Runtime base library for building Laravel packages.

## Contents

- [ARCHITECTURE.md](ARCHITECTURE.md) — high-level structure of the runtime.
- [SERVICES.md](SERVICES.md) — service reference (Asset, Component, Config, View, Event, Utility).
- [CONFIGURATION.md](CONFIGURATION.md) — fluent `Package` builder + lifecycle hooks.
- `adr/` — Architectural Decision Records (Phase 16 will populate).
- `examples/` — runnable examples (Phase 14 will populate).

## Online docs

- Primary: [`opensource.simtabi.com/package-tools/docs/`](https://opensource.simtabi.com/package-tools/docs/)
- Portal: [`opensource.simtabi.com/package-tools/`](https://opensource.simtabi.com/package-tools/)

## Quickstart

```bash
composer require laranail/package-tools
```

Then in your package's service provider:

```php
use Simtabi\Laranail\PackageTools\Package;
use Simtabi\Laranail\PackageTools\PackageServiceProvider;

class FooServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('foo')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_foos_table')
            ->hasInstallCommand(fn ($cmd) => $cmd->publishConfigFile()->askToRunMigrations());
    }
}
```
