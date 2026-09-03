# Adding an Artisan command

Extend the toolkit's `Command` base, name it `vendor::slug.command`, and register it:

```php
use Simtabi\Laranail\Package\Tools\Commands\Command;

#[AsCommand(name: 'acme::hello.sync')]
class SyncCommand extends Command
{
    protected $signature = 'acme::hello.sync {--force}';
}
```

```php
$package->hasCommands(SyncCommand::class);
```

## Why the base class

Symfony's `Command::validateName()` matches `^[^:]++(:[^:]++)*$`, which rejects the
empty segment in `::`. The base writes the name past that validator, and the
command still dispatches because Symfony resolves an exact name before it tries
splitting on `:`.

Extend it, or `use SupportsNamespacedNames` if you already have a base class.

## Why not a short alias

An alias like `hello:sync` hands back exactly the collision the namespaced name
exists to prevent — Artisan's command table is a flat map. If you want an alias,
scope it too.

## More

[Command naming](../tools/command-naming.md) · [Public names](../tools/public-names.md)

---

[← Docs index](../../README.md#documentation)
