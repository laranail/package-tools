# Changelog

All notable changes to `laranail/package-tools` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **`Services\Database\ChunkedBatchDispatcher`** runs one job per chunk of a large seeding workload,
  either as a queued `Bus::batch()` (`dispatch()`) or inline (`runInline()`, which returns a
  `ValueObjects\ChunkRunResult`). `drain()` works a queue until it is empty. Progress is tracked in
  items through `SeederRunTracker`. The dispatcher's batch callbacks capture only scalars, so they
  serialize onto any queue.
- **`SeederRunTracker::advance()` takes `int $by = 1`**, so a run can count rows or files rather than
  one unit per call. A non-positive step records nothing. Existing callers are unchanged.

- **`Commands\Concerns\ReadsOptions`** — one concern for console-input normalisation, applied by
  the base `Command`. Seven accessors: `strOption()`, `stringOption()`, `strArg()`, `boolOption()`,
  `intOption()`, `arrayOption()`, `listOption()` and `strArgOrNull()`.

  `strArgOrNull()` is the nullable counterpart to `strArg()`, which is shaped for a REQUIRED
  argument and so never sees `''`. An OPTIONAL argument is the case that needs absence preserved,
  because the next step is usually a fallback — and `argument('key') ?? config(...)` covers an
  omitted argument but not `artisan cmd ""`. Five commands in `laranail/license-verifier` had
  exactly that.

  `intOption()` carries a conditional return type (`@return ($default is null ? int|null : int)`),
  so a caller passing a non-null default does not have to restate it to satisfy a parameter typed
  `int`.

  `strOption()`, `strArg()` and `listOption()` came from `laranail/db-tools`, where they were the
  only copy in the family and nothing else could reach them. `stringOption()` moved off the base
  `Command` into the trait, so both string accessors sit together and the difference between them is
  visible. `boolOption()`, `intOption()` and `arrayOption()` are new, and were chosen by measuring
  the family rather than by guessing — 54 raw `(bool)` casts at option call sites, 14 `(array)` and
  13 `(int)`.

  Each replaces a cast that is wrong in a specific case and silent about it: `(bool) 'false'` is
  **true**, so `--force=false` sets the flag; `(int) 'twenty'` is `0`, which looks like a deliberate
  limit, port or timeout; `(string)` on `--connection=` yields `''`, which forks anything keyed on
  the resolved connection. See `docs/tools/command-options.md`.

  It lands here rather than in `laranail/console`, where command concerns otherwise live, because
  neither package can depend on the other — both keep `require` free of every `laranail/*` entry —
  and `stringOption()` is here. 18 of the 19 packages whose commands extend console's base already
  require this package.

- **`Testing\AssertsDriverContract::assertNoNullOnlyOptionGuards()`** — a third source-scanning
  guard, for the failure mode no type system sees. Symfony returns `null` for an option that was not
  supplied and `''` for one supplied without a value (`--days=`, which a shell produces readily from
  an unset variable). Code defaulting with `??`, `!== null` or `=== null` covers only half of "the
  caller gave me nothing", and `''` walks through into a cast that turns it into `0`, `0.0` or
  `false`. The command then succeeds on a number nobody chose.

  `laranail/license-verifier` shipped both halves: `watch --cycles=` never terminated, and
  `reminder skip --days=` wrote an already-expired reminder. `laranail/license-kit` issued a signing
  key with an empty `kid`.

  Scans source, because the defect is in a branch the suite does not take. `?? ''` is exempt, since
  the default is the empty string and the two cases coincide. A genuinely correct null-only test is
  annotated with `@option-guard-exempt` **on its own line or in the comment block above it** — not
  by filename, because a whole-file skip also covers reads added to that file later, which is how a
  guard stops guarding without anyone deciding that it should.

- **`Testing\AssertsDriverContract`** — a trait guarding the two package defects a mocked test
  structurally cannot reach: a driver name the configuration can produce but the manager cannot
  build, and a config key read where nothing is registered.

  `assertEveryDriverIsBuildable()` checks each name against the `create*Driver()` method
  `Illuminate\Support\Manager` interpolates it into, studly-casing the way Manager does, and
  **fails on an empty list** so a fixture that silently read nothing cannot pass as coverage.
  `assertReadsConfigAtRegisteredKey()` scans the package source for reads at the bare key, matching
  dotted keys of any depth and interpolated suffixes, with an `$exempt` list for the segments that
  belong to a different registry (container tags, gate abilities).

  Both are covered by tests that assert they *fail* correctly, not merely that they pass — a shared
  helper that cannot fail hands a green tick to every package that adopts it. See
  `docs/tools/driver-contract.md`, which also sets out when to prefer an exhaustive `match` over an
  enum to a `Manager` in the first place.

  `IsolatedTestCase` now `use`s it, alongside `AssertsPublishedConfigOverrides`, so the 24 packages
  extending the shared base get both assertions without a `uses()` line. No method name collides
  anywhere in the family — checked before wiring it in.

- **`Validation\Predicates\Pattern`** -- canonical patterns and one total matcher, so
  `console` and `validation` can share them without either depending on the other.

- **A shared `pint.json`**, no longer `export-ignore`d, consumed org-wide via
  `--config vendor/laranail/package-tools/pint.json`. See `docs/tools/pint.md`.

- **`vendor/bin/laranail-dist-integrity`** — verifies that every path `composer.json` references
  survives `git archive`, so a package cannot ship a manifest pointing at a file a dist install
  strips. Checks `extra.phpstan.includes`, `bin`, `autoload.psr-4`/`psr-0` and `autoload.files`.

  This existed as `scripts/verify-dist-integrity.php`, pasted into 15 packages. Every copy was
  byte-identical once whitespace was stripped — what differed was formatting, because each package's
  own `pint.json` reformatted it differently. One variant tripped `no_blank_lines_after_phpdoc` and
  took seven repositories red at once, with no shared place to fix it. A file copied into fifteen
  repositories cannot satisfy fifteen formatters.

  The logic now lives in `DistIntegrityAuditor` behind a `RevisionReader` seam, so it is unit-testable
  without a git checkout. The binary deliberately runs **without `vendor/`** — a check that guards
  what a dist install receives must not be blocked by a dependency resolution failure.

- **`php artisan laranail::package-tools.packages`** and the `PackageRegistry` behind it: every
  package built on `PackageServiceProvider`, what each one claimed, and whether any two claimed the
  same name.

  Laravel keeps view namespaces, translation namespaces, config keys and publish tags in flat global
  maps, so a second package claiming a key does not collide loudly — it silently replaces the first,
  and the failure surfaces far away as a missing view or the wrong file published. Nothing in the
  framework can answer that afterwards. `--collisions` exits non-zero, so it works as a CI gate.

  The report reads description, authors, licence, keywords and docs from each package's own
  `composer.json` rather than asking for them again — that file is the copy composer already forces
  an author to keep correct. `describedAs()`, `maintainedBy()`, `documentedAt()` and
  `withStability()` override it where a package wants to say something different at runtime.

  See [Package registry](docs/tools/package-registry.md).

- `Package::componentPrefix()`, the hyphen form of the view namespace. Blade component tags are the
  one registry that cannot take a slash: `ComponentTagCompiler` captures the name with
  `[\w\-\:\.]`, so `<x-laranail/atlas::card />` truncates at the slash and is emitted as literal
  text rather than compiled. `bootPackageViews()` registers the prefix as an alias over the paths
  `loadViewsFrom()` just resolved -- the published override directory included -- so both spellings
  find the same file and an override still wins for component tags. A custom view namespace is
  mirrored rather than ignored, so a package that opts out of the default still gets a tag-safe
  prefix.
- `tests/Feature/NamespaceSeparatorTest.php`, pinning the split against Blade's own name pattern
  rather than against a comment, so an upstream change to that pattern fails a test here.

### Changed

- **`Command::stringOption()` falls back to `$default` for `--flag=`**, not just when the option is
  absent. Previously the documented fallback did not apply to an option written without a value, so
  `stringOption('format', 'csv')` answered `''` for `--format=`. Pass `''` explicitly to keep an
  empty string. The method is otherwise unchanged and still resolves on the base `Command`, which
  now applies the trait that holds it.

- **`laranail/console` is a suggestion, not a requirement.** It was reached by exactly one class out
  of roughly 270 -- `SeederConsoleFormatter`, which renders styled seeder output -- and this package
  is the base class for the whole family, so that one file put a console library into every
  application that installed anything built on `PackageServiceProvider`, whether or not it ever
  seeded anything.

  `PlainSeederConsoleFormatter` implements the same contract with no dependency, and the container
  binds whichever is available. Styled output where laranail/console is installed, plain lines where
  it is not. Verified against a real `--no-dev` install, not just a `class_exists` branch.

  **The `require` block now contains no `laranail/*` entry at all**, and a test asserts that, because
  the cost of one is paid at install time by consumers who never boot the class responsible. In a
  Laravel application every remaining requirement is already present: `laravel/framework` provides
  every `illuminate/*` and `symfony/process`.

  Nothing changes for anyone already depending on `laranail/console` directly.

- **Breaking.** The default view and translation namespaces are now the composer package name,
  `vendor/package`, rather than `vendor-package`, so a key names the package that ships it:
  `view('laranail/atlas::page')`, `__('laranail/atlas::messages.saved')`. Published files follow the
  namespace into `resources/views/vendor/laranail/atlas` and `lang/vendor/laranail/atlas`, which is
  where Laravel then reads them from -- `FileLoader::loadNamespaceOverrides()` interpolates the
  namespace into `{$path}/vendor/{$namespace}/{$locale}/{$group}.php`, and `loadViewsFrom()` does the
  same for views -- so the nesting groups a vendor's packages under one directory instead of
  scattering them across the `lang/vendor` root. A package passing an explicit namespace to
  `hasViews()` is unaffected.

- **Breaking.** The package's OWN config publish tag is now
  `laranail::package-tools-config` (was the bare `package-tools-config`) — the same
  namespacing this package mints for everyone else's tags, enforced by a live-registry test.
  `vendor:publish --tag=package-tools-config` becomes
  `vendor:publish --tag=laranail::package-tools-config`.

### Removed

- **`Enums\Timezone`** (419 cases, 454 lines) and its generator. It was already
  `@deprecated` in favour of `laranail/chrono`'s, which has identical case names and
  values plus `city()`, `kind()`, `canonical()` and the alias map. Nothing in this
  package used it, and nothing anywhere in the org did -- verified before removal.
  A timezone enum has no business in a package-authoring toolkit that 51 packages
  install. Migration is a one-line `use` change; `timezone()` also takes a plain
  string. The `sync-check` script and its static-analysis step go with it.

### Fixed

- **`assertNoNullOnlyOptionGuards()` scanned files that have nothing to do with the console.**
  `$this->option()` is not exclusively `Illuminate\Console\Command`'s — an adapter, value object or
  widget may expose its own `option()` over an options array, and the guard matched on call shape
  alone. `laranail/captcha`'s ReCaptcha adapters are the worked example: they read a widget's
  options, extend `SiteVerifyAdapter`, and were flagged for a defect that cannot exist there.

  Now only command-shaped files are scanned — `extends *Command`, or a declared `$signature`.
  Verified against every package that has adopted the guard: no file they read options in falls
  outside that shape, so nothing stops being covered.

  This is a whole class of false positive removed rather than exempted one line at a time. An
  exemption should mark a real guard that is correct anyway, not a file the guard should never have
  opened.

- **`docs/tools/config-namespacing.md` described the default backwards, and two packages shipped a
  broken config because of it.** It said `hasConfigFile('foo')` registers `config('foo.*')`, "flat
  — unchanged, exactly like Laravel". It does not: `setName()` *requires* `vendor/package` and
  rejects a bare name, so `configVendor` is never null, `hasConfigNamespacing()` reduces to the
  `$configNamespacing` flag, and that flag defaults to **true**. A package named `acme/widget`
  registers at `config('acme.widget.*')`.

  Reading the bare key is silent — `config()` returns null or the call site's inline default — so
  `laranail/env-tools` and `laranail/env-kit-webui` both ran entirely on inline defaults, with
  protected keys that were therefore writable and secret-masking that masked nothing. Both are
  fixed in their own repositories; this is the documentation that produced them.

  `tests/Feature/ConfigNamespacingDefaultTest.php` now pins the behaviour, including that a bare
  package name throws, so the page cannot drift from the code again.

## [0.1.0] - 2026-08-15

### Changed — breaking

- **The config key is `laranail.package-tools`,** published to `config/laranail/package-tools.php`;
  `config('package-tools.seeders.autorun.in_tests')` is now
  `config('laranail.package-tools.seeders.autorun.in_tests')`. Laravel's config repository is a flat
  map, and this package — which exists to namespace *other* packages' config — was reading a bare key
  of its own.

  The deferred-hook **event names** (`package-tools.test.event`) are unchanged; they are a different
  registry and not part of this change.

- **The three defaults that register a package's name into a shared registry are
  now vendor-scoped.** Laravel keeps view namespaces, translation namespaces and
  Artisan command names in **flat maps keyed by the name**, so a second package
  claiming the same key does not collide loudly — it silently replaces the first,
  and the damage surfaces far away as a missing view, an untranslated string, or
  a command that runs someone else's code. A bare slug like `icons`, `console` or
  `auth` is a plausible collision with a sibling package, a third-party one, or
  the consuming application's own.

  | Call | Was | Is |
  |---|---|---|
  | `hasViews()` | `view('widget::…')` | `view('acme-widget::…')` |
  | `translationNamespace()` | `acme/widget` | `acme-widget` |
  | `InstallCommand` default signature | `widget:install` | `acme::widget.install` |

  Each separator is forced by the registry that parses it, not chosen for
  consistency — do not unify them. A command name may use `::` because Symfony
  resolves an exact name before splitting on `:`. A translation namespace may
  **not** use a slash: `lang/vendor/{namespace}` is a single published
  directory, so `acme/widget` nests the published files one level deeper than
  `vendor:publish` and every consumer's override path expect. That one was a
  real bug, not only a convention.

  Passing an explicit argument still wins in both cases, so
  `hasViews('widget')` and `hasTranslations('widget')` restore the old names —
  but prefer not to, since that re-introduces exactly the collision the default
  exists to prevent. `InstallCommand`'s `$signature` parameter is unchanged.

  `InstallCommand` now uses `SupportsNamespacedNames`, without which Symfony's
  `validateName()` rejects the empty segment in `::`.

  **Upgrading:** a package that called `hasViews()` / `hasTranslations()` with no
  argument and referenced the old names must update its `view()` / `__()` calls
  and any published paths, or pass the old slug explicitly. Anything documenting
  `{package}:install` needs the new name.

### Fixed

- **External publish tags were invisible to the publish command, and `--tag=` rejected them.**
  `ServiceProvider::publishableGroups()` already returns the tag *names*; both call sites wrapped it
  in `array_keys()`, which yields `0, 1, 2 …` — and every one of those failed the `is_string()`
  guard that followed. So `--list` never printed the `(external)` rows its own comment describes,
  and `knownTags()` was always empty of them, meaning `--tag=livewire:assets` was refused as unknown
  even though the application published it.

### Added

- **`--external`** on `laranail::package-tools.publish` — publish every tag this package did not
  register. Replaces the pattern of hardcoding provider class names
  (`Livewire\LivewireServiceProvider`, `Laravel\Horizon\HorizonServiceProvider`), which published
  exactly the packages someone thought of and guarded each with `class_exists` so a missing one
  failed silently. Combines with `--all` to publish both sets.

- **`Services\Doctor\Checks\UnregisteredPublishableCheck`** — reports package directories that
  registered no publish tag. The failure is silent by construction: a module whose provider forgot
  `setPublishTagId()` works fine and simply never publishes, so the symptom arrives later as a
  missing asset. Warns rather than fails, because a module with nothing to publish is ordinary, and
  **never publishes or deletes anything** — the command this idea comes from answered the same
  question by publishing each directory under a guessed tag name.

### Security

- **Two recursive-delete paths were bypassing `PublishPathGuard`.** The guard's
  docblock has always claimed it is the one place in this package that deletes
  anything, and that it exists because a registered destination of `''` resolves
  to the document root. Both claims were false.

  `AssetRegistry::cleanup()` and `HasAssetPublisher::cleanAsset()` called
  `File::deleteDirectory()` directly — no containment check, no `..` rejection,
  no minimum depth, and no `is_link()` dispatch, so a symlinked destination was
  followed and its target emptied. `cleanAsset()` took the registered
  destination straight into `public_path()`, where `''` is the document root.

  Both now route through the guard. A target outside every configured prune root
  is **skipped and reported** rather than deleted: packages publish into
  `config/` and `database/migrations/` as well as `public/vendor/`, and silently
  removing a published config file is a worse surprise than declining to.

  `AssetRegistry::cleanup()` now returns `list<string>` — the refused targets —
  instead of `void`. It is not on `RegistryInterface`, and the one caller
  ignored the return, so nothing breaks.

### Fixed

- **`Services\Config\ConfigService::forget()` could not remove a top-level
  key.** It pruned a copy of `all()` and re-seeded the survivors — but a removed
  top-level key is simply absent from that copy, so nothing ever touched it and
  `get()` kept returning the old value. Nested keys worked, which is why it went
  unnoticed.

- **Booting a package no longer deletes published assets.**
  `PackageServiceProvider::bootPackageCustomPublishes()` and
  `ProcessAssets::bootPackageAssetRegistry()` deleted the destination of every
  publish tag marked `cleanBeforePublish` / `clean: true`. The only guard was
  `runningInConsole()`, and every console command boots every provider — so
  `php artisan route:list` removed the published assets of any package that had
  asked for a clean, and they did not come back until someone re-published.

  Boot now records the request rather than acting on it. See
  [UPGRADING.md](UPGRADING.md#boot-no-longer-deletes-published-assets) for what
  changes for a package that used the flag.

### Added

- **`Services\Config\ConfigManager`** (+ `Contracts\ConfigManagerInterface`) —
  a fluent, chainable runtime configuration manager, relocated here from
  `laranail/toolkit`. Config machinery belongs with the package-authoring
  runtime, which already owns the resolver, merger, validator and pattern
  resolver under `Services/Config/`.

  It sits alongside `ConfigService` rather than replacing it, and the boundary is
  now documented: `ConfigService` is boot-time `mergeConfigFrom` semantics where
  app config wins, `ConfigManager` is runtime override where the caller wins.
  Bound with `bind()`, not `singleton()` — it carries a base path and an
  operation log, so two callers configuring two module roots must not share one.

  Two things changed on the way over. `remove()` now makes both `get()` and
  `has()` miss for a top-level key, where before it could only null the value —
  the pruned array replaces the repository's item store via
  `Services\Config\ConfigItemStore`, degrading to nulling against a custom
  `Repository`. And `dump()` / `dd()` were added.

  See [docs/tools/config-manager.md](docs/tools/config-manager.md).

- **`Services\Config\ConfigItemStore`** — the one place that knows how to remove
  a config key, so `ConfigManager::remove()` and `ConfigService::forget()` cannot
  drift into disagreeing about what "forget" means.

- **`Services\Asset\PublishTagRegistry`** (+ `PublishTagEntry`) — a singleton
  recording every publish tag a laranail package registers, which package owns
  it, and whether it asked for its destination to be cleaned first. Laravel's own
  `ServiceProvider::$publishGroups` records tag => paths but knows nothing about
  ownership or cleaning. Repeat records for one tag merge their paths, and
  `cleanable` is sticky — one call site asking for a clean is enough.

- **`Services\Asset\PublishPathGuard`** (+ `PublishRoot`, `Exceptions\UnsafeAssetPath`)
  — every destructive asset operation now proves a path may be deleted before
  deleting it. A publish root must normalise cleanly, resolve inside the project,
  survive a non-overridable deny-list (the project root, `app`, `bootstrap`,
  `config`, `database`, `node_modules`, bare `public`, `resources`, `routes`,
  `src`, `storage`, `tests`, `vendor`), sit at least two levels below the project
  root, and still land inside its root once symlinks are followed.

  The deny-list is deliberately not configurable: config can narrow the blast
  radius, never widen it. Containment is strict descendancy with a trailing
  separator, so a root of `public/vendor` does not capture `public/vendor2`, and
  the root itself is never deletable — only its contents. An empty root list
  makes nothing deletable, which fails closed.

- **`laranail::package-tools.publish`** — publish package assets by tag,
  package, or all at once, with `--list`, `--dry-run` and `--json`.

  **`--force` overwrites and `--clean` deletes; they are separate flags.**
  Conflating them is how published assets get lost: in the implementation this
  replaces, `--force` meant "recursively delete every destination, then
  republish", and it ran for every module in the application. A destination
  outside every configured prune root is skipped and reported rather than
  deleted, because packages publish to `config/` and `database/migrations/` too.

- **`laranail::package-tools.assets-prune`** (+ `Services\Asset\OrphanScanner`,
  `OrphanReport`, `OrphanEntry`) — finds published files that nothing publishes
  any more. Every other cleanup here is destination-registry driven and so can
  only remove what something registered in the current process; an uninstalled
  package registers nothing, and its files stay forever.

  The expected set comes from **every** publish group the application exposes,
  not just laranail's, or Livewire's and Horizon's asset directories would read
  as orphans. It reports by default — `--prune` deletes, `--force` skips the
  confirmation, production refuses without `--force`, and a run exceeding
  `assets.prune.max_deletions` aborts before deleting anything. Symlinks are
  recorded as leaves and never descended.

- **`Concerns\Database\InteractsWithSeedFiles`** — a memoized Faker generator
  plus fixture-file resolution for package seeders. `fake()` **throws**
  `SeederException::missingFaker()` when `fakerphp/faker` is absent rather than
  installing it; the code this generalises ran `composer install` from inside the
  method and then called `exit(1)`, taking the process with it. Memoization is
  for reproducibility, not speed: `Factory::create()` reseeds the RNG per call.

- **`package-tools.assets.*` and `package-tools.seeders.{files_path,faker_locale}`**
  config blocks.

### Changed

- **A symlink inside a publish root is now deletable.** `PublishPathGuard`
  previously refused any path resolving outside its root, which caught symlink
  leaves too — so a stray link in `public/vendor` could never be removed, since
  every route to deleting it went through that check. `delete()` dispatches on
  `is_link()` and unlinks, which never touches the target, so the leaf is now
  exempt from resolution while its parent is still checked and an intermediate
  directory swap is still refused.

Initial public release.
