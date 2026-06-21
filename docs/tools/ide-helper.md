# laranail::package-tools.ide-helper

Facade generator. Walks classes annotated with `#[AsFacade]` under a source directory and
emits a Laravel `Facade` subclass per contract — with `@method`
docblocks generated from the contract's public methods so IDEs can
autocomplete the static surface. Backed by
`Simtabi\Laranail\Package\Tools\Services\Facade\FacadeAutoGenerator`.

```bash
php artisan laranail::package-tools.ide-helper \
    [--source=src] \
    [--source-namespace=App\\] \
    [--output=src/Facades] \
    [--facade-namespace=App\\Facades]
```

| Flag | Default | Meaning |
|---|---|---|
| `--source=DIR` | `src` | Directory to scan recursively (relative to `base_path()` or absolute). |
| `--source-namespace=NS` | `App\\` | PSR-4 root namespace mapped to `--source`. |
| `--output=DIR` | `src/Facades` | Where to write the generated facades (relative or absolute). |
| `--facade-namespace=NS` | `App\\Facades` | Namespace stamped onto the generated classes. |

Relative `--source` / `--output` paths are resolved against `base_path()`.
On success the command lists the generated facades and exits `SUCCESS`;
when no annotated contracts are found it prints a notice and still exits
`SUCCESS`. Any `Throwable` (e.g. an invalid alias/accessor/namespace) is
reported and the command exits `FAILURE`.

## The `#[AsFacade]` attribute

`Simtabi\Laranail\Package\Tools\Attributes\AsFacade`
(`#[Attribute(Attribute::TARGET_CLASS)]`):

```php
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsFacade
{
    public function __construct(
        public string $alias,
        public ?string $accessor = null,
    ) {}
}
```

- `alias` — the generated facade's class name (and file name `<alias>.php`).
- `accessor` — the facade accessor returned by `getFacadeAccessor()`.
  When omitted, it defaults to the annotated class's own FQCN.

```php
use Simtabi\Laranail\Package\Tools\Attributes\AsFacade;

#[AsFacade(alias: 'Greeter')]
interface GreeterContract
{
    public function greet(string $name): string;
}

// Bind to a container key instead of the contract's class-string:
#[AsFacade(alias: 'Counter', accessor: 'counter.service')]
interface CounterContract { /* … */ }
```

## What is generated

For each `#[AsFacade]` hit, one `final class <alias> extends Facade` is
written to `<output>/<alias>.php` under `<facade-namespace>`. The class:

- documents each non-static, non-constructor, non-destructor public
  method of the contract as `@method static <return> <name>(<params>)`,
  with union/intersection/nullable types and parameter defaults
  rendered;
- carries a `@see \<Contract>` tag;
- implements `getFacadeAccessor(): string`.

`getFacadeAccessor()` returns either `\Fully\Qualified\Name::class`
(when the accessor looks like a class/interface name) or a quoted string
literal (when it is a container binding key such as `counter.service`).

### Sample run

```bash
$ php artisan laranail::package-tools.ide-helper \
    --source=src \
    --source-namespace='Acme\Hello\\' \
    --output=src/Facades \
    --facade-namespace='Acme\Hello\Facades'

Generated 1 facade(s):
  ✔ Greeter → /pkg/src/Facades/Greeter.php
```

For the `GreeterContract` above, `src/Facades/Greeter.php` is written as:

```php
<?php

declare(strict_types=1);

namespace Acme\Hello\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Auto-generated facade for Acme\Hello\GreeterContract.
 *
 * @method static string greet(string $name)
 *
 * @see \Acme\Hello\GreeterContract
 */
final class Greeter extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Acme\Hello\GreeterContract::class;
    }
}
```

Bind the accessor in `packageRegistered()` so the facade resolves at
runtime: `$this->app->bind(GreeterContract::class, Greeter::class)`.

## Validation

Because the alias, accessor, and facade namespace are interpolated into
generated PHP source, each is validated before generation:

- `facade-namespace` — every `\`-separated segment must be a valid PHP
  identifier; empty namespaces are rejected.
- `alias` — must match `^[A-Za-z_]\w*$` (a bare PHP identifier).
- `accessor` — must match `^[A-Za-z0-9_\\.:-]+$`; class-shaped accessors
  become `\…::class`, everything else becomes a quoted literal. Anything
  outside that character set is rejected with a `RuntimeException`.

These checks close the code-injection seam: nothing that could escape a
PHP-identifier (or string-literal) slot reaches the generated file.

## See also

`#[AsFacade]` is *not* wired by `discoversWithAttributes()` — facade
generation is an explicit command step.

- [attribute-discovery.md](attribute-discovery.md) — the other three
  discovery attributes (`#[AsArtisanCommand]`, `#[AsRoute]`,
  `#[AsViewComposer]`) and how `discoversWithAttributes()` wires them.
- [runtime-services.md](runtime-services.md) — the `HasGuzzleConfig` /
  `HasErrorStorage` traits, another way contracts surface to consumers.
- [examples/Contracts/GreeterContract.php](../examples/Contracts/GreeterContract.php)
  — a contract carrying `#[AsFacade]` for generation.

[← Docs index](../../README.md#documentation)
