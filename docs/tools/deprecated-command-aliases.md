# Deprecated command aliases

A command extending `Simtabi\Laranail\Package\Tools\Commands\Command` prints one line naming its replacement when it is invoked by a deprecated alias, then runs as before.

## Which aliases warn

| Alias of `laranail::atlas.doctor` | Warns | Why |
|---|---|---|
| `atlas:doctor` | yes, automatically | outside the vendor scope |
| `laranail:atlas.doctor` | no | a scoped spelling variant |
| `laranail::atlas.check` | only if listed | scoped, but a retired name |

An alias outside the command's vendor scope warns without being listed. The naming convention allows
no new bare alias, so one that exists is there only for backwards compatibility. Making every such
command opt in would leave the convention applied to half the family. A scoped alias that is a
retired name is listed explicitly:

```php
use Simtabi\Laranail\Package\Tools\Commands\Command;

final class DoctorCommand extends Command
{
    protected $signature = 'laranail::atlas.doctor';

    protected $aliases = ['atlas:doctor', 'laranail::atlas.check'];

    protected function deprecatedAliases(): array
    {
        return ['laranail::atlas.check'];
    }
}
```

A command whose own name has no vendor segment cannot tell a scope apart. It warns only for the
aliases it lists.

## The warning

```text
Deprecated: [atlas:doctor] is a deprecated alias and will be removed no earlier than the next minor after 0.1. Use [laranail::atlas.doctor] instead.
```

Override `deprecatedAliasRemoval()` to word the removal differently. `isDeprecatedAlias(string)` answers
the same question for a test.

## How it works

The `WarnsOnDeprecatedAliases` trait reads the name the caller typed. The input's first argument is
the command token for `php artisan`, `Artisan::call()` and `$this->call()` alike, while `getName()` is
always the canonical name. Symfony calls `initialize()` after binding the input and before
`interact()`, so the warning prints before any prompt. A command that overrides `initialize()` must
call `parent::initialize()` to keep it.

The trait is generalised from laranail/package-scaffolder's `WarnsOnDeprecatedAlias`. Use it directly
on a command that extends something other than the base.

---

[← Docs index](../../README.md#documentation)
