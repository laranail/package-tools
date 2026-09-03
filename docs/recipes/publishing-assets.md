# Publishing assets

Declare the group, then let the application take it:

```php
$package->name('acme/hello')->setPublishTagId('hello')->hasAssets();
```

```bash
php artisan vendor:publish --tag=acme::hello-assets
```

`hasAssets()` publishes `resources/dist` to `public/vendor/<shortName>`. If your
package builds somewhere else, declare the group yourself in `packageBooted()` —
the tag shape is what matters, not which helper produced it.

## Tags are derived, not typed

`setPublishTagId('hello')` plus `->name('acme/hello')` yields `acme::hello-assets`,
`acme::hello-config`, `acme::hello-views`. You do not write those strings, and you
should not: a hand-written tag is a bare global name in a flat registry.

Check what the framework actually holds rather than reading the provider:

```bash
php artisan packages          # every package, and what each one claimed
```

## Re-publish after every upgrade

`vendor:publish` never deletes, so a file you removed stays behind in the
application. Pass `--force` on upgrade, and treat the published directory as
disposable output.

`php artisan package:assets:prune` removes orphans the package no longer ships.

## More

[Publishing](../tools/publishing.md) · [Package registry](../tools/package-registry.md)

---

[← Docs index](../../README.md#documentation)
