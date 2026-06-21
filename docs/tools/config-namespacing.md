# Namespaced & nested config

By default a package registers **flat** config files — `hasConfigFile('foo')`
merges `config/foo.php` and you read `config('foo.key')`, exactly like Laravel.
That's unchanged.

On top of that, package-tools can mount config files that live in
**sub-directories** at a **dotted key derived from their folder path**, so
`config('admin.panel.key')` resolves to `config/admin/panel.php`. The mapping
happens once at boot (each file is merged under its dotted key), so reads stay
native `config()` — there's no runtime resolver and no performance cost.

## The three modes

| Builder call | Reads `config/…` | Resolves as |
|---|---|---|
| `hasConfigFile('foo')` | `config/foo.php` | `config('foo.*')` *(flat — unchanged)* |
| `hasNestedConfig('panel', 'admin')` | `config/admin/panel.php` | `config('admin.panel.*')` |
| `hasNestedConfigs(['panel','users'], 'admin')` | `config/admin/{panel,users}.php` | `config('admin.panel.*')`, `config('admin.users.*')` |
| `hasConfigDirectory('admin')` | every file directly in `config/admin/` | `config('admin.<file>.*')` *(one level)* |
| `discoversConfig()` | the whole `config/` tree, recursively | `config('a.b.file.*')` |
| `discoversConfig('acme')` | the whole tree, recursively | `config('acme.a.b.file.*')` |
| `discoversConfig('acme', 'modules')` | `config/modules/` recursively | `config('acme.<rel>.file.*')` |

```php
public function configurePackage(Package $package): void
{
    $package
        ->name('acme/widget')
        ->hasConfigFile()                 // config/widget.php       → config('widget.*')
        ->hasNestedConfig('panel', 'admin') // config/admin/panel.php → config('admin.panel.*')
        ->discoversConfig();              // mounts the rest of config/ by folder
}
```

### Folder → key mapping

The folder path (minus `.php`) becomes the dotted key:

| File (under `config/`) | Key |
|---|---|
| `settings.php` | `settings` |
| `admin/panel.php` | `admin.panel` |
| `api/v1/limits.php` | `api.v1.limits` |

So `config/api/v1/limits.php` returning `['rate' => 60]` is read as
`config('api.v1.limits.rate')`.

## Optional: an explicit key

`hasNestedConfig()` takes a third argument to override the folder-derived key:

```php
$package->hasNestedConfig('panel', 'admin', key: 'dashboard.settings');
// config/admin/panel.php → config('dashboard.settings.*')
```

`discoversConfig($namespace)` adds a root prefix to every discovered file
(`discoversConfig('acme')` → `config('acme.admin.panel.*')`).

## Optional: an in-file `__namespace`

A config file may **opt** to declare its own mount point with the reserved key
`__namespace`. It's entirely optional — without it the folder structure decides
the key. When present, it overrides the folder-derived key and is **stripped
before merge**, so it never appears in the resolved config.

```php
// config/anything/here.php
return [
    '__namespace' => 'acme.blog',   // optional — mounts this file at config('acme.blog.*')
    'per_page' => 15,
];
// config('acme.blog.per_page') === 15
// config('acme.blog.__namespace') === null   (stripped)
```

The value must be a safe dotted string (`[A-Za-z0-9_-]` segments); anything else
is ignored and the folder-derived key is used. Avoid mounting onto a core key
(e.g. `app`, `database`) — you'd merge into the framework's own config.

### Mount-key precedence

For a given file the key is resolved at merge time as:

1. an in-file `__namespace` (if present and valid), else
2. an explicit builder `key:` / `discoversConfig($namespace)` prefix, else
3. the folder-derived dotted key.

Nested keys are **not** prefixed with the package's `vendor.package` namespace —
that prefix applies only to the flat `hasConfigFile()` path.

## Merge semantics

Package values are **defaults**: each file is merged as
`array_merge($fileArray, $existing)`, so an application's own config (or a
published copy) overrides the package's. The merge is skipped when the app's
config is cached (`config:cache`), matching Laravel's `mergeConfigFrom()`.

## Reading config as raw data (without mounting)

Sometimes you want the config **arrays themselves** — to inspect, diff or
transform them — rather than registering them into `config()`. `loadConfigData()`
is the read-and-return counterpart of `discoversConfig()`: same folder→key
mapping, but it returns the data and registers nothing.

```php
// From inside the package (builder convenience) — reads this package's config/:
$data = $package->loadConfigData('admin');
// ['admin.panel' => ['title' => 'Admin', ...], ...]

$all = $package->loadConfigData();          // whole config/ tree, recursive
$top = $package->loadConfigData('', false); // top level only (non-recursive)
```

The same is available off the container's `ConfigService` for any package root,
and on the lower-level `ConfigFileResolver`:

```php
app(ConfigService::class)->loadFrom('/path/to/package', 'admin');   // {baseDir}/config/admin
(new ConfigFileResolver($baseDir))->loadAll('admin');               // [key => array]
(new ConfigFileResolver($baseDir))->load('panel', 'admin');         // single file → array
```

Notes:
- Keys match `discoversConfig()` (`config/api/v1/limits.php` → `api.v1.limits`).
- Data is returned **as-is** — an in-file `__namespace` key is **not** stripped
  (that is a mount-time concern); nothing lands in the config repository.
- A missing folder returns `[]`; a matched file that is unreadable or doesn't
  `return [ ... ]` throws `Exceptions\InvalidPath`.
- Prefer `config('vendor.package.key')` for normal access — reach for this only
  when you genuinely need the raw arrays.

## Publishing

`php artisan vendor:publish --tag=<vendor>::<package>-config` publishes nested
files **preserving their folder layout** — `config/admin/panel.php` →
`config_path('admin/panel.php')` — so the published tree mirrors how the keys
resolve.

## Backward compatibility

Flat `hasConfigFile()` is untouched; existing single-file packages behave
exactly as before. The nested entries live in a separate registry
(`Package::$namespacedConfigFiles`) that the flat pipeline ignores.

## Security

Folder, file and `__namespace` values are validated with
`Support\PathResolver::validatePathSecurity()` — traversal (`..`), absolute
paths, drive letters, null bytes and URL schemes are rejected before a path is
built.

[← Docs index](../../README.md#documentation)
