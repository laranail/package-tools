# Config manager

`Simtabi\Laranail\Package\Tools\Services\Config\ConfigManager` (contract
`Contracts\ConfigManagerInterface`) is a fluent, chainable manager for runtime
configuration. Every mutator returns `$this`.

```php
use Simtabi\Laranail\Package\Tools\Contracts\ConfigManagerInterface;

app(ConfigManagerInterface::class)
    ->setBasePath(base_path('platform/modules/core'))
    ->override('horizon.path', '/')
    ->merge('app', ['providers' => [MyProvider::class]])
    ->when(app()->isLocal(), fn ($c) => $c->override('app.debug', true))
    ->remove('services.unused');
```

Resolve it fresh each time — it carries a base path and an operation log, so it
is bound with `bind()` rather than `singleton()`. Two callers configuring two
module roots must not share one instance.

## How this differs from `ConfigService`

Both write to `config()`, so it is worth being explicit about which owns what.
They are not competing APIs — they run at different times with opposite intent,
and mixing them up is the only way they conflict.

| | [`ConfigService`](services.md) | `ConfigManager` |
|---|---|---|
| When | boot, from a package provider | runtime, from the application |
| Merge | `array_merge($file, $existing)` — **app config wins** | override — **the caller wins** |
| Owns | a package mounting its own defaults | an app deliberately reshaping config |

`ConfigService` is `mergeConfigFrom` semantics: a package ships defaults and
yields to whatever the application already set, which is exactly what makes
published config work. `ConfigManager` is for the application saying "no, this
value, now" — so it overrides by design.

## Reading and writing

| Method | Returns | Notes |
|---|---|---|
| `get(string $key, mixed $default = null)` | `mixed` | |
| `has(string $key)` | `bool` | |
| `set(string $key, mixed $value)` | `static` | |
| `override(string $key, mixed $value)` | `static` | Semantic alias of `set()`. |
| `setIfMissing(string $key, mixed $value)` | `static` | |
| `setMany(array $values)` / `overrideMany(array $values)` | `static` | |
| `merge(string $key, array $values)` | `static` | True deep merge. |
| `push(string $key, mixed $value)` / `prepend(...)` | `static` | |
| `remove(string $key)` / `forget(string $key)` | `static` | See below. |
| `all()` | `array` | |

`merge()` uses [`ConfigMerger`](../configuration.md) for a real recursive merge
rather than `array_merge_recursive`, which folds two duplicate string keys into
an array instead of letting the later one win:

```php
// config: ['mail' => ['host' => 'a', 'port' => 25]]
$config->merge('svc', ['mail' => ['host' => 'b']]);
// => ['mail' => ['host' => 'b', 'port' => 25]]
// array_merge_recursive would have given host => ['a', 'b']
```

## Removing a key

`remove()` makes both `get()` and `has()` miss, at any depth.

That is harder than it looks, and worth knowing about if you implement anything
similar. `Repository::set()` only ever adds or overwrites, so re-seeding a
pruned copy of `all()` leaves a removed **top-level** key exactly where it was.
`offsetUnset()` is literally `set($key, null)`, which leaves `has()` reporting
`true`. And passing the whole array to `set()` re-seeds every surviving key
while doing nothing at all for the one you asked to remove.

So the pruned array replaces the repository's own item store, via
`Services\Config\ConfigItemStore`. Against a custom `Repository` implementation
with a different shape it degrades to nulling the key — `get()` misses, `has()`
does not — which is the best the framework contract itself offers.

## Loading files

| Method | Notes |
|---|---|
| `loadAndOverride(string $configKey, string $filePath)` | Path is absolute, or relative to `setBasePath()`. Throws `InvalidPath` when missing or not an array. |
| `loadPackageConfig(string $configKey, string $folder = 'config/packages')` | `{folder}/{configKey}.php`. |
| `loadPackageConfigs(array $configKeys, string $folder = 'config/packages')` | |
| `loadConfigFile(string $file): array` | Raw array from `{basePath}/config/{file}.php`. **Lenient** — an absent file yields `[]`. |

## Conditionals and transforms

```php
$config
    ->when($flag, fn ($c) => $c->set('a', 1))
    ->unless($flag, fn ($c) => $c->set('b', 1))
    ->inEnvironment(['local', 'testing'], fn ($c) => $c->set('debug', true))
    ->whenHas('mail.host', fn ($c, $host) => $c->set('mail.from', "noreply@{$host}"))
    ->transform('retries', fn (int $n): int => $n * 2)
    ->each('channels', fn (array $ch): array => [...$ch, 'audit' => true]);
```

## The operation log

Off by default. `withLogging()` records every mutation, which is how you find
out what reshaped a value at boot:

```php
$config->withLogging()->set('a', 1)->remove('b');

$config->getLog();
// [
//   ['operation' => 'set',    'key' => 'a', 'value' => 1],
//   ['operation' => 'remove', 'key' => 'b'],
// ]
```

A genuine `null` value **is** logged; the `value` key is omitted only when the
operation carries no value, so `set('k', null)` and `remove('k')` stay
distinguishable.

`dump(?string $key)` and `dd(?string $key)` print a key — or everything, with no
argument — the first continuing the chain, the second halting.

---
[← Docs index](../../README.md#documentation)
