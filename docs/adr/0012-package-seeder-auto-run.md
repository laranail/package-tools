# ADR-0012 — Package-author seeder plumbing lives in `package-tools`

- **Status:** Accepted
- **Date:** 2026-05-07
- **Deciders:** Maintainer
- **Scope:** `package-tools` — `src/Services/Database/`, `Concerns/Package/HasFactoriesAndSeeders.php`

## Context

Two existing classes (`BaseSeeder`, `DatabaseSeedHelper`) implement a
fluent helper for **registering, discovering, and executing seeders**
contributed by Laravel packages. The substantive value is the container
hook (`$app->resolving(DatabaseSeeder::class, ...)`) that runs every
package's seeders the first time the host app's root `DatabaseSeeder`
resolves — i.e. when `php artisan db:seed` runs without `--class`.

`database-tools` is positioned (ADR-0010) as **independent** — Eloquent
helpers and schema macros usable by any Laravel app, with no dependency
on `package-tools`. The seeder-auto-run feature is package-author
plumbing: `setSeederConfiguration($key, $seeders, $namespace)` only
makes sense when the seeders being registered come from third-party
packages. Putting it in `database-tools` would either leak the
package-author concept into a generic DB utility or strip the auto-run
hook from the helper, leaving a less-novel "another fluent seeder
runner".

The 1135-line monolith also needed splitting; its concerns mix
configuration, discovery, execution, container-hooking, FK-toggling,
and console output, all in one class.

## Decision

Land the feature in `package-tools` as four small services + one
support class + two events:

| File | Responsibility |
|---|---|
| `src/Services/Database/SeederRegistry.php` | In-memory store of `key → {seeders, namespace, options}` |
| `src/Services/Database/SeederPathDiscoverer.php` | Walk a directory, return `Seeder` subclass FQCNs |
| `src/Services/Database/SeederExecutor.php` | Run a registry; bucket by namespace; optional FK-toggle and event emission |
| `src/Services/Database/SeederResolverHook.php` | One-shot `$app->resolving(DatabaseSeeder::class, …)` listener |
| `src/Support/ForeignKeyCheckGuard.php` | Re-entrant FK toggle over a Closure |
| `src/Events/SeedingStarted.php`, `SeedingFinished.php` | Concrete event classes |

Wire it into the existing `HasFactoriesAndSeeders` trait via two new
fluent methods and a real `bootPackageSeeders()`:

```php
$package->hasPackageSeeders('vendor/pkg', [...$seederClasses], namespace: 'Vendor\\Pkg');
$package->discoverPackageSeedersIn(__DIR__ . '/../database/seeders', namespace: 'Vendor\\Pkg');
```

`bootPackageSeeders()` is already invoked from
`PackageServiceProvider::bootPackageDeferredHooks()` (the orphan-fix
landed earlier), so the executor runs at boot time.

## Consequences

- **Wins**: Spatie-compatible `loadSeedersFrom()` / `registerSeeder()`
  remain unchanged; the new package-namespaced API is additive. The
  resolver hook means consumers don't need to remember `--class=` —
  `php artisan db:seed` "just works" for every laranail-built package
  installed in the host app.
- **Costs**: 7 new tracked files, ~600 LOC. The original 1135-line
  monolith is **not** vendored verbatim — the rewrite drops its
  external coupling (`Simtabi\Core\SeederOutputHelper`,
  `Simtabi\Laranail\Nails\Events\DatabaseEvents`, the bespoke
  `ConsoleProgressBar`) in favour of stdlib + Laravel facades + the
  in-repo `ProgressIndicator`.
- **Migration**: existing consumers of `DatabaseSeedHelper::make($app)
  ->setSeeders(...)->autoSeed()` rewrite as `$package->hasPackageSeeders
  ('key', [...]) ` — same end-state, fluent through `Package` rather
  than a separate builder.
- **Reversibility**: trivial — the new files don't replace anything.
  Removing them leaves the existing seeder API intact.

## Alternatives considered

- **Drop into `database-tools`** — rejected. Would force a `package-tools`
  dep (the registry/executor pair is package-aware) or strip the
  auto-run hook (kills the value).
- **Vendor the 1135-line monolith verbatim** — rejected. External
  dependencies (`Simtabi\Core\Support\SeederOutputHelper`,
  `Simtabi\Laranail\Nails\Events\DatabaseEvents`,
  `Simtabi\Laranail\Nails\Support\Console\ConsoleProgressBar`) wouldn't
  resolve. The split here is the minimum-viable transplant that compiles
  against the existing `package-tools` surface.
- **Split between both repos** — `SeederPathDiscoverer` could live in
  `database-tools` as a generic utility. Rejected for v1.0 to keep
  ADR-0010's "no `package-tools` ↔ `database-tools` coupling" intact.
  Revisit if a non-package-author consumer asks for it.
