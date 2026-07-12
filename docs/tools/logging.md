# Per-package logging

`$package->log()` gives every package its own beautifully formatted logfile, so a host app can always tell which package an entry came from — backed by `Simtabi\Laranail\Package\Tools\Services\Log\PackageLogger` and configured with the fluent `LogDefinition`.

## Quick start

```php
public function configurePackage(Package $package): void
{
    $package
        ->setName('acme/blog')
        ->hasLogging(LogDefinition::make()->daily(30)->level('info'));

    // "Early logging" works right here, before the app finishes booting:
    $package->log()->info('Blog package configuring', 'Register');
}
```

At runtime, anywhere in the package (or host):

```php
$package->log()->error('CountrySeeder failed', 'Seeder', ['exception' => $e]);
$package->log()->success('Migrations published', 'Install', ['count' => 3]);

app('laranail.logger.acme-blog')->warning('Cache cold', 'Cache');
```

## What the lines look like

```
[2026-07-08 14:03:22.512] [acme/blog] [INFO] Blog routes registered
[2026-07-08 14:03:22.514] [acme/blog] [SUCCESS] [Install] Migrations published | {"count":3}
[2026-07-08 14:03:22.518] [acme/blog] [ERROR] [Seeder] CountrySeeder failed | {"exception":"RuntimeException: boom"}
```

Fixed bracket prefix for humans (millisecond timestamp, package, level, optional label), one compact JSON tail for machines — only when context remains. `LogDefinition::make()->asJson()` swaps the whole line for Monolog's `JsonFormatter` when machine-first output is preferred.

## Methods

Every PSR-3 level plus `success()`, each with an optional label as the second argument:

| Method | Level token |
|---|---|
| `emergency` / `alert` / `critical` / `error` / `warning` / `notice` / `info` / `debug` | the PSR-3 level |
| `success(string $message, ?string $label = null, array $context = [])` | `SUCCESS` (written at INFO) |
| `log(string $level, string $message, ?string $label = null, array $context = [])` | any |

> The label-as-second-argument signature is deliberately NOT PSR-3. When an interface consumer needs a `Psr\Log\LoggerInterface`, use `$package->log()->channel()`.

## `LogDefinition` reference

| Method | Effect |
|---|---|
| `daily(int $days = 14)` | daily-rotated file (the default) |
| `single()` | one unrotated file |
| `path(string $absolutePath)` | full logfile path override |
| `directory(string $dir)` | keep the default filename, move the directory |
| `level(BackedEnum\|string $level)` | minimum level (default `debug`) |
| `useChannel(BackedEnum\|string $channel)` | delegate to an existing host channel |
| `asJson()` | JsonFormatter output |
| `permission(int $mode)` | unix mode for the created file |
| `disabled()` | ship the definition off by default |
| `whenConfig(string $key, bool $default = true)` | gate on a host config key |

## Configuration precedence

Highest wins:

| Layer | Where |
|---|---|
| Host-defined channel | `logging.channels.{vendor}-{package}` in the host's `config/logging.php` — wins wholesale, never touched |
| Per-package host config | `{vendor}.{package}.logging.*` (`enabled`, `channel`, `path`, `directory`, `driver`, `days`, `level`, `format`, `permission`) |
| Global host config | `package-tools.logging.*` (shipped `config/package-tools.php`) |
| Fluent `LogDefinition` | the package author's defaults |
| Built-in defaults | daily, 14 days, `debug`, line format, `storage/logs/{vendor}-{package}.log` |

The logger registers a **real named channel** `logging.channels.{vendor}-{package}` (only when the host hasn't defined that key), so it composes everywhere: `Log::channel('acme-blog')`, stack channels, Laravel's `tap` config.

## Early logging

Lines written inside `configurePackage()` are buffered (with their original timestamps) and flushed at boot, once every provider registered and all config is final — so host overrides apply even to the earliest lines. The buffer is bounded (100 records; overflow switches to write-through) and a write after the app has booted force-flushes, so records are never stranded by a mid-register crash.

## Failure safety

Logging never throws. Channel resolution degrades to Laravel's emergency logger; a failed write falls back to the default `Log` channel and is then swallowed. `enabled => false` (any layer) short-circuits before a file is even created.

---

[← Docs index](../../README.md#documentation)
