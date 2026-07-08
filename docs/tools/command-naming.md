# Artisan command naming

Commands across the laranail family use one shape:

```
laranail::<package-slug>.<command>
```

- `laranail` — the org namespace.
- `::` — the namespace separator.
- `<package-slug>` — the composer slug suffix, so the source package is
  unambiguous: `package-tools`, `package-scaffolder`, `database-tools`, …
- `.<command>` — the command itself, after a dot.

This package's own commands follow it: `laranail::package-tools.doctor`,
`laranail::package-tools.sbom`, `laranail::package-tools.audit`,
`laranail::package-tools.ide-helper`, `laranail::package-tools.seed`.

## The problem

Symfony Console rejects the `::` separator. `Command::setName()` runs
`validateName()`, whose regex `^[^:]++(:[^:]++)*$` allows a single `:`
between segments but rejects the empty segment that `::` produces. A
command named `laranail::package-tools.doctor` would never register.

## The base command

`Simtabi\Laranail\Package\Tools\Commands\Command` is an abstract base that
extends `Illuminate\Console\Command` and mixes in the
`SupportsNamespacedNames` trait. Extend it instead of Laravel's command
to opt in:

```php
use Simtabi\Laranail\Package\Tools\Commands\Command;

final class SyncCommand extends Command
{
    protected $signature = 'laranail::package-tools.sync';

    protected $description = 'Sync the package state.';

    public function handle(): int
    {
        // …
        return self::SUCCESS;
    }
}
```

All five built-in `laranail::package-tools.*` commands extend this base.

## The trait

`Simtabi\Laranail\Package\Tools\Commands\Concerns\SupportsNamespacedNames`
is what the base class mixes in. Use it directly on a command that
already extends something else:

```php
use Illuminate\Console\Command;
use Simtabi\Laranail\Package\Tools\Commands\Concerns\SupportsNamespacedNames;

final class SyncCommand extends Command
{
    use SupportsNamespacedNames;

    protected $signature = 'laranail::package-tools.sync';
}
```

The trait overrides `setName(string $name): static` and
`setAliases(iterable $aliases): static`. Each writes the private `name` /
`aliases` property on Symfony's `Command` directly through a closure
bound to that class's scope, sidestepping `validateName()`. Both the `::`
and the plain `:` forms are accepted.

Dispatch still works: Symfony's `find()` resolves an exact command name
before its `:`-splitting lookup runs, so
`php artisan laranail::package-tools.doctor` matches the registered name
verbatim.

## See also

- [doctor.md](doctor.md), [sbom.md](sbom.md), [audit.md](audit.md),
  [ide-helper.md](ide-helper.md) — the four built-in commands, each named
  with the `::` separator.
- [attribute-discovery.md](attribute-discovery.md) —
  `#[AsArtisanCommand]` for registering commands without a `hasCommand()`
  call.
- [examples/Console/SyncCommand.php](../examples/Console/SyncCommand.php) —
  a consumer command with the `acme::hello.sync` namespaced signature.

[← Docs index](../../README.md#documentation)
