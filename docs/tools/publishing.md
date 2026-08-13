# Asset publishing and pruning

Two commands and four services covering the whole life of a published file: which tag put it there,
whether it may be deleted, and whether anything still publishes it. Backed by
`Simtabi\Laranail\Package\Tools\Services\Asset\{PublishTagRegistry, PublishPathGuard, PublishRoot, OrphanScanner}`.

## `--force` overwrites. `--clean` deletes.

They are separate flags because conflating them is how published assets get lost. In the
implementation this replaces, `--force` meant "recursively delete every destination, then republish",
and it was invoked for every module in the application — so an operator reaching for the flag that
means *yes, overwrite, I know* got a recursive delete they never asked for.

`--force` now does exactly what it does in `vendor:publish`. Deleting is `--clean`, it is confirmed
separately, and it only ever happens inside a configured prune root.

## `laranail::package-tools.publish`

```bash
php artisan laranail::package-tools.publish [--tag=*] [--package=] [--all] [--list] [--clean] [--force] [--dry-run] [--json]
```

| Flag | Meaning |
|---|---|
| `--tag=TAG` | Publish this tag. Repeatable. |
| `--package=NAME` | Publish every tag belonging to this package. |
| `--all` | Publish every registered laranail publish tag. |
| `--list` | Show what is publishable — laranail tags with their package, destination count, and whether they sit in a prune root, plus every external tag the application exposes. |
| `--clean` | Delete each tag's destinations before publishing. Confirmed unless `--force`. |
| `--force` | Overwrite existing files. With `--clean`, skip the confirmation. |
| `--dry-run` | Print the plan and change nothing. |
| `--json` | Machine-readable output for `--list` and `--dry-run`. |

Selection is required: a bare invocation fails rather than publishing everything.

Publishing itself delegates to `vendor:publish` — not by subclassing `VendorPublishCommand`, whose
`publishTag()` is protected and free to change in any minor release.

### What `--clean` will not delete

A destination outside every configured prune root is **skipped and reported**, not deleted. Packages
publish to `config/` and `database/migrations/` as well as `public/vendor/`, and a clean that silently
removed a published config file would be a much worse surprise than one that says it left it alone.

```
$ php artisan laranail::package-tools.publish --tag=blog-config --clean --force
  skipped cleaning /app/config/blog.php — The path [/app/config/blog.php] is not inside the publish root [/app/public/vendor], so it will not be deleted.
  published blog-config
```

## `laranail::package-tools.assets-prune`

Finds published files that nothing publishes any more — the old copy of an asset whose path changed,
or the whole tree left behind by a package that has been uninstalled. Every other cleanup here is
destination-registry driven, so it can only remove what something registered *in the current process*;
an uninstalled package registers nothing.

```bash
php artisan laranail::package-tools.assets-prune [--tag=*] [--prune] [--force] [--strict] [--json]
```

| Flag | Meaning |
|---|---|
| `--tag=TAG` | Narrow the expected set to these tags. Repeatable. |
| `--prune` | Actually delete. Without it the command only reports. |
| `--force` | Skip the confirmation, and allow the run in production. |
| `--strict` | Exit non-zero when orphans exist. For a CI gate. |
| `--json` | Emit the report as JSON. |

```
$ php artisan laranail::package-tools.assets-prune
+------------------+-----------+---------+---------------+
| Path             | Kind      | Size    | Probably from |
+------------------+-----------+---------+---------------+
| oldpackage       | directory | 41.2 KB | —             |
| blog/removed.css | file      | 1.1 KB  | blog-assets   |
+------------------+-----------+---------+---------------+
2 orphaned entr(y|ies), 12 file(s), 42.3 KB.

Nothing was deleted. Re-run with --prune to remove these.
```

### Why it reports by default

The decision is a set difference over a booted application's state, and that state can be wrong. A
package whose provider failed to boot publishes nothing — so everything it ever published looks
orphaned. A command that acted on that inference unasked would be one bad boot away from an incident.

So there are four independent brakes, and `--force` only releases the second:

1. `--prune` is required before anything is deleted.
2. A confirmation, skipped by `--force`, and refused outright in a non-interactive shell without it.
3. Production refuses without `--force`.
4. A run exceeding `assets.prune.max_deletions` aborts **before deleting anything**, rather than
   discovering the problem partway through.

### How the expected set is built

From **every** publish group the application exposes, not only the ones laranail registered — via
`ServiceProvider::publishableGroups()`, plus the registry for tags that booted and were later cleared.
This is the correctness hinge. Livewire, Horizon, Filament and anything else publishing into
`public/vendor` are legitimate occupants; a scan that only knew about laranail tags would report every
one of them as an orphan and offer to delete it.

A directory whose files are all stale collapses to a single entry. A directory that still holds one
published file does not — only its stale children are reported.

### What it will not do

- **Follow a symlink.** A link is recorded as a leaf and never descended; descending would report the
  contents of somewhere outside the root as orphaned. Pruning one unlinks it and leaves its target
  alone.
- **Understate itself.** A scan that hit its depth ceiling says `truncated`, because otherwise it
  reads exactly like a clean one.
- **Run with a misconfigured root.** A root that fails validation fails the command, rather than
  degrading to an empty scan that looks like success.

## The path guard

Everything destructive goes through `PublishPathGuard`, and every root through `PublishRoot::make()`:

1. Non-empty, no null byte.
2. Relative paths resolve against `base_path()`.
3. Lexical normalisation — `.` and `..` collapse without touching the filesystem, since a publish root
   may not exist yet.
4. Containment in `base_path()`.
5. A **non-overridable** deny-list: the project root itself, `app`, `bootstrap`, `config`, `database`,
   `node_modules`, bare `public`, `resources`, `routes`, `src`, `storage`, `tests`, `vendor`.
6. Minimum depth of 2 below the project root.
7. A symlink walk, on deletion, confirming the path still lands inside the root once resolved.

The deny-list is not configurable on purpose: **config can narrow the blast radius, never widen it.**
A typo turning `public/vendor` into `public` should not be one config edit away from deleting the
application. That is not hypothetical — the code this replaces computed
`$target === '' ? public_path() : …` and handed the result to a recursive delete, so a single module
with empty publish config would take the entire document root with it.

Containment is strict descendancy with a trailing separator, so a root of `public/vendor` does not
capture `public/vendor2`, and the root itself is never deletable — only its contents.

An empty root list means nothing is deletable. That fails closed: an empty list is far more likely to
be a misconfiguration than an instruction to delete from everywhere.

## Registering tags

`PackageServiceProvider` overrides `publishes()`, so every tag any laranail package registers is
captured automatically — through the fluent builder, through any `Process*` trait, and through any
future call site. There is nothing to call by hand.

```php
$registry = app(PublishTagRegistry::class);

$registry->tags();                  // ['blog-assets', 'blog-config', …]
$registry->forPackage('blog');      // that package's tags
$registry->get('blog-assets')->destinations();
```

## Configuration

See [Asset publishing and pruning](../configuration.md#asset-publishing-and-pruning) for the
`assets.publish` and `assets.prune` blocks.

---

[← Docs index](../../README.md#documentation)
