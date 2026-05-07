# Commands reference

Four Artisan commands ship with `laranail/package-tools` and auto-register via
`extra.laravel.providers` in `composer.json`. No manual wiring needed —
`composer require laranail/package-tools` is sufficient.

```text
package:doctor       Run package health checks
package:sbom         Generate a CycloneDX 1.5 SBOM
package:audit        Audit composer.lock against OSV.dev
package:ide-helper   Generate Facade classes from #[AsFacade] contracts
```

---

## `package:doctor`

Runs every `DoctorCheck` registered against the singleton `DoctorService`
and prints a status report. Exits non-zero when any check returned `FAIL`.

```bash
php artisan package:doctor [--json] [--strict]
```

| Flag | Meaning |
|---|---|
| `--json` | Emit JSON instead of the colourised TTY view. |
| `--strict` | Treat `WARN` as failure (exit non-zero). |

Register checks from a package's service provider:

```php
$this->app->make(DoctorService::class)->register(new MigrationsAreFreshCheck());
// or, fluently per-package:
$package->hasDoctorCheck(MigrationsAreFreshCheck::class);
```

---

## `package:sbom`

Reads the host project's `composer.json` + `composer.lock` and emits a
CycloneDX 1.5 JSON Software Bill of Materials. Pure-PHP — no shelling out.

```bash
php artisan package:sbom [--output=sbom.json] [--print]
```

| Flag | Meaning |
|---|---|
| `--output=PATH` | Write to `PATH` (relative to `base_path()` or absolute). Default: `sbom.json`. |
| `--print` | Emit the JSON to stdout instead of writing a file. |

Output conforms to the [CycloneDX 1.5 JSON
spec](https://cyclonedx.org/docs/1.5/json/) — the `serialNumber` is a
fresh RFC-4122 v4 UUID per run. Dev dependencies appear with `scope: optional`.

---

## `package:audit`

Posts every `name@version` pair from `composer.lock` to OSV.dev's
[`/v1/querybatch`](https://google.github.io/osv.dev/post-v1-querybatch/)
endpoint and surfaces any advisories returned. Exits non-zero when at
least one package is affected.

```bash
php artisan package:audit [--no-dev] [--json] [--timeout=30]
```

| Flag | Meaning |
|---|---|
| `--no-dev` | Skip `packages-dev` (audit production deps only). |
| `--json` | Emit machine-readable JSON. |
| `--timeout=N` | HTTP timeout in seconds (1–600). Default: 30. |

Network-supplied advisory text is stripped of ANSI/control characters
before being printed, and OSV ids are URL-encoded before being assembled
into advisory links. Treat the output as trusted only after that.

---

## `package:ide-helper`

Walks classes annotated with `#[AsFacade(alias: '…')]` under a source
directory and emits a Laravel `Facade` subclass per contract — with
`@method` docblocks generated from the contract's public methods so IDEs
can autocomplete the static surface.

```bash
php artisan package:ide-helper \
    [--source=src] \
    [--source-namespace=App\\] \
    [--output=src/Facades] \
    [--facade-namespace=App\\Facades]
```

| Flag | Meaning |
|---|---|
| `--source=DIR` | Directory to scan recursively. Default: `src`. |
| `--source-namespace=NS` | PSR-4 root namespace mapped to `--source`. Default: `App\\`. |
| `--output=DIR` | Where to write the generated facades. Default: `src/Facades`. |
| `--facade-namespace=NS` | Namespace stamped onto the generated classes. Default: `App\\Facades`. |

Aliases, accessors, and the facade namespace are validated against PHP
identifier rules before generation — the generator refuses anything that
could escape a PHP-identifier slot.

```php
#[AsFacade(alias: 'Greeter')]
interface GreeterContract
{
    public function greet(string $name): string;
}

// Default accessor: the contract's class-string.
// Override with #[AsFacade(alias: 'Counter', accessor: 'counter.service')]
// to bind to a container key instead — generator emits a string literal.
```
