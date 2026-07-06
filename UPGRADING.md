# Upgrading

## 1.x to 2.0

Four behavior changes; everything else in 2.0 is additive.

### 1. Seeders no longer execute at package boot

`bootPackageSeeders()` ran every registered seeder at every app boot — DB
writes per request. In 2.0 all package seeders run at `db:seed` time via the
seeder resolver hook; the boot-time execution path is gone.

`hasPackageSeeders()` is now the definition-based API with db:seed-time
semantics:

```php
// before (1.x — ran at boot)
$package->hasPackageSeeders('acme/blog', [BlogSeeder::class], 'Acme\\Blog', ['fire_events' => true]);

// after (2.0 — runs with the host's `php artisan db:seed`)
$package->hasPackageSeeders(
    AutoSeederDefinition::make('acme/blog')
        ->seeders([BlogSeeder::class])
        ->inNamespace('Acme\\Blog')
        ->options(['fire_events' => true]),
);
// or the simple shorthand
$package->hasPackageSeeders('acme/blog', [BlogSeeder::class]);
```

If you truly need seeders at boot, run them yourself in `packageBooted()`
via `PackageSeeder::seeders()->classes([...])->execute()` — an explicit,
visible decision instead of a hidden side effect.

### 2. Middleware registration is uniformly deferred

The enhanced middleware methods (`registerMiddlewareAlias(es)`,
`registerMiddlewareGroup(s)`, `addToMiddlewareGroup`,
`registerPrefixedMiddleware`) applied to the router at configure time. In
2.0 they store on the package like everything else and apply in
`bootPackageMiddleware()` — aliases, groups, and global middleware in one
boot step. Code relying on an alias existing DURING `configurePackage()`
must move that reliance to boot time.

### 3. `hasComponentNamespace()` now actually works

Pre-2.0 the registration was written to an array no boot step ever read —
the advertised API did nothing. It now registers through
`Blade::componentNamespace()` at boot. If you called it and shipped a
workaround (registering the namespace manually), remove the workaround or
you will register twice (harmless, but redundant).

### 4. Seeder registry entries are typed bundles

`SeederRegistry::get()`/`all()` return `SeederBundle` value objects instead
of shape-arrays. `register()` keeps its signature. Options are scoped per
bundle: one package's `disable_foreign_key_checks`/`fire_events`/
`parameters` no longer leak into other packages' runs (this was a bug),
and bundles execute in `priority` order (lower first, ties keep
registration order).

```php
// before
$registry->get('acme/blog')['seeders'];
// after
$registry->get('acme/blog')->seeders();
```

---

[← Docs index](README.md#documentation)
