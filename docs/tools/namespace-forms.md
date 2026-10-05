# Namespace forms

A package's views and translations answer to both spellings of its own name: the canonical `vendor/package` and the `vendor-package` alias.

## Why both

The slash form is canonical. It names the composer package, and published overrides nest by vendor
under `lang/vendor/laranail/atlas`. The hyphen form is what a Blade tag can spell, and what fourteen
view and five translation registrations in the family used before the slash form was the default.
With both registered, a package can move its call sites to the canonical name without breaking a host
that still writes the old one. See [Public names](public-names.md) for why the separators differ.

## What the provider registers

| Declared | View namespaces | Translation namespaces |
|---|---|---|
| `hasViews()` / `hasTranslations()` | `laranail/atlas`, `laranail-atlas` | `laranail/atlas`, `laranail-atlas` |
| `hasViews('laranail-atlas')` | `laranail-atlas`, `laranail/atlas` | |
| `hasTranslations('atlas-short')` | | `laranail/atlas`, `laranail-atlas`, `atlas-short` |
| `hasViews('acme-legacy')` | `acme-legacy` only, unchanged | |

The aliases are registered over the paths the primary namespace resolved, including the
application's published override directory, so every spelling finds the same file. A custom view
namespace that is not the package's own name, such as a vendored upstream name, gets nothing added.
That name is the package's to keep.

Each translation namespace reads published overrides from its own directory. The canonical one reads
from `lang/vendor/laranail/atlas`, the alias from `lang/vendor/laranail-atlas`, which is where a host
that published under the old name already has them.

`$package->viewNamespaces()` and `$package->translationNamespaces()` list what will be registered.

## Registering by hand

A provider that calls `loadViewsFrom()` or `loadTranslationsFrom()` itself adds the missing form in
one call:

```php
use Simtabi\Laranail\Package\Tools\Support\NamespaceForms;

$this->loadViewsFrom($views, 'laranail-enumerator');
$this->loadTranslationsFrom($lang, 'laranail-enumerator');

NamespaceForms::mirror($this->app, 'laranail/enumerator');
```

Whichever form is registered, the other is added over the same paths, in both registries. A
registry that holds neither form, or both, is left alone. Call it after the `load*From()` calls.
Those defer until the view factory and translator are resolved, and so does this, in the same order.

## Asserting it

```php
$scope = NamingScope::for('laranail/enumerator', 'Simtabi\\Laranail\\Enumerator\\');

expect($this->assertViewNamespacesScoped($scope, atLeast: 2))
    ->toContain('laranail/enumerator', 'laranail-enumerator');
```

See [Naming assertions](naming-assertions.md).

---

[← Docs index](../../README.md#documentation)
