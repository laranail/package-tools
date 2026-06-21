# laranail::package-tools.doctor

Health-check runner. Runs every registered `DoctorCheck` against the
singleton `Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorService`
and prints a status report. Exits non-zero when any check returned `FAIL`
(or, with `--strict`, when any returned `WARN`).

```bash
php artisan laranail::package-tools.doctor [--json] [--strict]
```

| Flag | Meaning |
|---|---|
| `--json` | Emit JSON instead of the coloured TTY view. |
| `--strict` | Treat `WARN` as failure (exit non-zero). |

When no checks are registered, the TTY view prints a notice and exits
`SUCCESS`.

## Sample run

```text
$ php artisan laranail::package-tools.doctor

laranail::package-tools.doctor — running 2 check(s)…

  ✓  config:published — Config published with all required keys
       path: /app/config/hello.php
  !  cache:warmed — Route cache is cold
       hint: php artisan route:cache

  Summary: 1 pass, 1 warn, 0 fail, 0 skip
```

The exit code is `0` here (a `WARN` is not a failure). Re-run with
`--strict` to make the `WARN` exit non-zero — useful as a CI gate.

## Registering a check

A `DoctorCheck` is registered against the `DoctorService` singleton.
From a package's `configurePackage()`, use the fluent helper:

```php
$package->hasDoctorCheck(MigrationsAreFreshCheck::class);
// or an instance:
$package->hasDoctorCheck(new MigrationsAreFreshCheck());
```

Equivalently, resolve the singleton directly:

```php
$this->app->make(DoctorService::class)->register(new MigrationsAreFreshCheck());
```

`DoctorService::register(DoctorCheck|class-string<DoctorCheck> $check)`
accepts either an instance or an FQCN. An FQCN is instantiated with `new`
(no constructor arguments); a class that does not exist or does not
implement `DoctorCheck` throws `InvalidArgumentException`.

## The `DoctorCheck` contract

`Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorCheck`:

```php
interface DoctorCheck
{
    public function name(): string;          // short label, e.g. "config:published"
    public function description(): string;   // one-line human description
    public function run(): DoctorResult;     // never throws
}
```

A check should never throw — if `run()` does throw, `DoctorService`
catches the `Throwable` and synthesises a `FAIL` result whose detail
carries the `exception` class, `file`, and `line`.

## Result and status model

`Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorResult` is a
readonly value object:

```php
new DoctorResult(DoctorStatus $status, string $message, array $detail = [])
```

Construct it with the named factories:

| Factory | Status |
|---|---|
| `DoctorResult::pass(string $message, array $detail = [])` | `Pass` |
| `DoctorResult::warn(string $message, array $detail = [])` | `Warn` |
| `DoctorResult::fail(string $message, array $detail = [])` | `Fail` |
| `DoctorResult::skip(string $message, array $detail = [])` | `Skip` |

`detail` is an optional `array<string, scalar|array<scalar>>` of
structured context; the TTY view renders each entry indented under the
check, the JSON view emits it verbatim.

`Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorStatus` is a
backed enum:

| Case | Value | Symbol | Colour |
|---|---|---|---|
| `Pass` | `pass` | `✓` | green |
| `Warn` | `warn` | `!` | yellow |
| `Fail` | `fail` | `✗` | red |
| `Skip` | `skip` | `·` | grey |

`symbol()` and `ansiColor()` back the TTY rendering.

## Example check

```php
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorResult;

final class ConfigPublishedCheck implements DoctorCheck
{
    public function name(): string
    {
        return 'config:published';
    }

    public function description(): string
    {
        return 'Verifies the package config has been published.';
    }

    public function run(): DoctorResult
    {
        return file_exists(config_path('foo.php'))
            ? DoctorResult::pass('Config is published.')
            : DoctorResult::warn('Config not published.', ['hint' => 'php artisan vendor:publish']);
    }
}
```

## JSON output shape

```json
{
  "summary": { "pass": 0, "warn": 0, "fail": 0, "skip": 0 },
  "checks": [
    {
      "name": "config:published",
      "description": "Verifies the package config has been published.",
      "status": "pass",
      "message": "Config is published.",
      "detail": {}
    }
  ]
}
```

## DoctorService API

| Method | Returns | Purpose |
|---|---|---|
| `register(DoctorCheck\|class-string $check)` | `self` | Add a check (instance or FQCN). |
| `run()` | `list<array{check, result}>` | Run every check in registration order. |
| `summarise(array $report)` | `array{pass,warn,fail,skip}` | Count results by status. |
| `getChecks()` | `list<DoctorCheck>` | Registered checks. |
| `reset()` | `self` | Clear all registered checks. |

## See also

- [configuration.md](../configuration.md) — the `hasDoctorCheck()` fluent
  step that wires a check from `configurePackage()`.
- [examples/Doctor/HelloHealthCheck.php](../examples/Doctor/HelloHealthCheck.php)
  — a complete runnable `DoctorCheck`.
- [examples/Doctor/StorageWritableCheck.php](../examples/Doctor/StorageWritableCheck.php)
  — a second check covering the `skip()` / `fail()` / `warn()` outcomes.
- [runtime-services.md](runtime-services.md) — `SystemService`, handy for
  building environment-aware checks.

[← Docs index](../../README.md#documentation)
