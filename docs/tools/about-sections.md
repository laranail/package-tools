# About sections

`$package->hasAboutSection(...)` adds a section to Laravel's `php artisan about` output — backed by the fluent `Simtabi\Laranail\Package\Tools\Support\Definitions\AboutSectionDefinition`, with per-field lazy closures, config gating, and failure-safe rendering.

## Quick start

```php
use Simtabi\Laranail\Package\Tools\Support\Definitions\AboutSectionDefinition;

public function configurePackage(Package $package): void
{
    $package->hasAboutSection(
        AboutSectionDefinition::make('Acme Blog')
            ->field('Version', '3.1.1')                        // string
            ->field('Posts', fn () => Post::count())           // int, resolved lazily
            ->field('API enabled', config('blog.features.api'))// bool → "true"/"false"
            ->field('Deployed', now())                         // date → ISO-8601
    );
}
```

`php artisan about` then shows:

```
  Acme Blog ..............................................
  Version ............................................. 3.0.0
  Posts ................................................. 128
  API ................................................... off
```

## Three ways to supply fields (mix freely)

```php
AboutSectionDefinition::make('Acme Blog')

    // 1. one field at a time — a scalar, or a Closure resolved lazily
    ->field('Version', '3.0.0')                        // static string
    ->field('Debug', config('app.debug'))              // typed bool → "true"/"false"
    ->field('Posts', fn (): string => (string) Post::count())

    // 2. a static array in one call
    ->fields([
        'Two-factor' => config('blog.two_factor') ? 'enabled' : 'disabled',
        'Queue'      => config('queue.default'),
    ])

    // 3. a whole-array lazy source (merged before individual fields, so
    //    explicit field() calls win on a name collision)
    ->fieldsUsing(fn (): array => ['Cache' => cache()->getStore()::class]);
```

Every closure — per-field or whole-array — is evaluated only when the `about` command actually runs, never at boot.

## Failure safety

A field closure that throws (e.g. `Post::count()` against an unmigrated database) renders the section's **fallback** instead of crashing `php artisan about`. You no longer need to hand-wrap each field in `rescue()`.

```php
AboutSectionDefinition::make('Acme Blog')
    ->fallback('n/a (not migrated)')                    // default is "n/a"
    ->field('Posts', fn (): string => (string) Post::count());
```

If the DB isn't migrated, that row shows `n/a (not migrated)` and every other field still renders. A throwing whole-array `fieldsUsing()` source is skipped entirely (its field names aren't known), while explicit `field()` calls are unaffected.

## Config gating

Hide the whole section unless a host config key allows it:

```php
->whenConfig('blog.about_panel')          // shown only when config is truthy (default true)
->whenConfigNotNull('blog.license_key')   // shown only when the key is non-null
```

Gating is evaluated at boot; a gated-off section is never registered with the `about` command.

## Field value types

A field value may be **any type** — or a `Closure` returning any type,
resolved lazily. Each renders to a display string:

| Value | Renders as |
|---|---|
| `string` / `int` / `float` | the value cast to string |
| `bool` | `true` / `false` |
| `null` | `null` |
| backed enum (`SeederExecutionMode::Queued`) | its backing value (`queued`) |
| pure enum (`Status::Active`) | its case name (`Active`) |
| `DateTimeInterface` | ISO-8601 (`2026-07-09T08:30:00+00:00`) |
| array | compact JSON (`{"a":1}`) |
| `Arrayable` | JSON of `toArray()` |
| `Stringable` / object with `__toString` | the cast string |
| any other object | compact JSON |

```php
AboutSectionDefinition::make('Acme Blog')
    ->field('Version', '3.1.1')                       // string
    ->field('Posts', fn () => Post::count())          // int (lazy)
    ->field('Mode', SeederExecutionMode::Queued)      // enum → "queued"
    ->field('Deployed', now())                        // Carbon (DateTimeInterface)
    ->field('Flags', ['api' => true, 'beta' => false])// array → JSON
    ->field('Maintainer', null);                      // → "null"
```

## Fluent reference

| Method | Effect |
|---|---|
| `AboutSectionDefinition::make(string $label)` | start a section under `$label` |
| `field(string $name, mixed $value)` | one field of **any type** (see above); a `Closure` is resolved lazily |
| `fields(array<string, mixed> $fields)` | register many fields at once |
| `fieldsUsing(Closure $source)` | a whole-array lazy source, merged before explicit fields |
| `fallback(string $fallback)` | placeholder for a field/source that throws (default `n/a`) |
| `whenConfig(string $key, bool $default = true)` / `whenConfigNotNull(string $key)` | gate the whole section on config |

## Backward compatibility

The original callback form still works unchanged:

```php
$package->hasAboutSection('Acme Blog', fn (): array => [
    'Version' => '3.0.0',
    'Posts'   => rescue(fn () => (string) Post::count(), 'n/a', false),
]);
```

Both forms render into the same `about` output. The fluent designer is preferred for new code — its per-field laziness and built-in fallback remove the `rescue()` boilerplate.

---

[← Docs index](../../README.md#documentation)
