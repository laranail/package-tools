# Public names

Seven registries a package writes into, the shape package-tools mints for each, and why three of
them cannot share one separator.

Laravel keeps view namespaces, translation namespaces, config keys, publish tags, command names,
middleware aliases, and Blade component prefixes in **flat, global maps keyed by the name**. Two
packages claiming one key do not collide loudly — the second silently replaces the first, and the
damage surfaces far away as a missing view, an untranslated string, or a security control attached
to nothing. Every name a package registers therefore carries the vendor *and* the package slug.

## What a package gets

Given `$package->name('laranail/atlas')`:

| Registry | Shape | Example |
|---|---|---|
| View namespace | `vendor/package` | `view('laranail/atlas::page')` |
| Translation namespace | `vendor/package` | `__('laranail/atlas::messages.saved')` |
| Blade component prefix | `vendor-package` | `<x-laranail-atlas::card />` |
| Config key | `vendor.package` | `config('laranail.atlas.enabled')` |
| Publish tag | `vendor::package-<suffix>` | `vendor:publish --tag=laranail::atlas-config` |
| Artisan command | `vendor::package.<command>` | `php artisan laranail::atlas.doctor` |
| Middleware alias | `vendor-package` | `->middleware('laranail-atlas')` |

The accessors are `viewNamespace()`, `translationNamespace()`, `componentPrefix()`,
`getDottedNamespace()`, and `getNamespacedPublishTag()`.

## Why the separators differ

The separator is **forced by each registry's parser**, not chosen for consistency. Unifying them
breaks things, silently in three of the four cases.

**Views and translations take a slash**, and the nesting it causes is the point. Laravel
interpolates the namespace into the override path itself — `FileLoader::loadNamespaceOverrides()`
reads `{$path}/vendor/{$namespace}/{$locale}/{$group}.php`, and `loadViewsFrom()` does the same for
views — so published files land in `lang/vendor/laranail/atlas` and are read from exactly there.
One directory per vendor beats a flat `lang/vendor` root holding thirty sibling packages.

**Blade component tags cannot.** `ComponentTagCompiler` captures the component name with
`[\w\-\:\.]`, which admits no forward slash, so `<x-laranail/atlas::card />` truncates at
`laranail` and is emitted as literal text rather than compiled — no error, just a tag that renders
as itself. `componentPrefix()` returns the hyphen form for this registry alone.

**Middleware aliases cannot take `::`.** Laravel does `explode(':', $name, 2)` to take middleware
parameters, the way `throttle:60,1` works, so `laranail::atlas` resolves as the middleware
`laranail` with the parameter `:atlas`, and the alias is never found.

**Commands do take `::`**, and only because Symfony resolves an exact command name *before* it
splits on `:` to search namespaces. Getting the name past `Command::validateName()` needs the
`SupportsNamespacedNames` trait; see [Command naming](command-naming.md).

## The alias that keeps both spellings working

`bootPackageViews()` registers the view namespace, then registers `componentPrefix()` as a second
namespace **over the paths `loadViewsFrom()` just resolved** — the application's published override
directory included. So `view('laranail/atlas::components.card')` and `<x-laranail-atlas::card />`
find the same file, and publishing an override still wins for component tags.

Where an application runs a custom view finder that has no `getHints()`, the alias falls back to the
package path alone: tags still resolve, but they stop seeing published overrides.

## Overriding the default

`hasViews('acme/legacy')` sets the namespace explicitly. `componentPrefix()` mirrors it rather than
ignoring it, so the tag-safe form becomes `acme-legacy` and component tags keep compiling.

Prefer not to override. A bare slug like `icons` or `auth` is a plausible collision with a sibling
package, a third-party one, or the consuming application's own.

## Asserting it

Grepping the provider proves how the registration was *written*, not what the framework ended up
holding. Assert against the live registries on a booted application instead:

```php
expect(View::getFinder()->getHints())->toHaveKey('laranail/atlas')
    ->and(Lang::getLoader()->namespaces())->toHaveKey('laranail/atlas')
    ->and(array_keys(ServiceProvider::publishableGroups()))->toContain('laranail::atlas-config')
    ->and(app('router')->getMiddleware())->toHaveKey('laranail-atlas');
```

Check the guard has teeth by registering a bare namespace and watching it fail.

---

[← Docs index](../../README.md#documentation)
