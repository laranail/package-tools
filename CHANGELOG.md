# Changelog

All notable changes to `laranail/package-tools` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial extraction from `laranail/packager` (Phase 4 of the laranail suite cleanup).
- Fluent `Package` builder + abstract `PackageServiceProvider` with **13 domain
  aggregator traits wiring 44 leaf traits** (ADR-004 + Phase 3b). Six leaves
  stay unwired by design (ADR-0011) due to method/property collisions.
- **Tier A (v1.0)** — attribute-driven discovery (`#[AsArtisanCommand]`,
  `#[AsRoute]`, `#[AsFacade]`, `#[AsViewComposer]`), `package:doctor`,
  `IsolatedTestCase`, append-only `.env` writer (`EnvFileService`).
- **Tier B (v1.1)** — `package:sbom` (CycloneDX 1.5 JSON, pure-PHP),
  `package:audit` (OSV.dev `/v1/querybatch`, exits non-zero on advisory).
- **Tier C (v1.2)** — `package:ide-helper` + `FacadeAutoGenerator` walks
  `#[AsFacade]` contracts and emits Laravel Facade subclasses with `@method`
  docblocks for IDE autocomplete.
- `LaranailToolsServiceProvider` auto-registers the four library commands via
  `extra.laravel.providers`; binds `DoctorService` as a singleton.
- `bootPackageDeferredHooks()` in the provider boot chain now wires middleware
  aliases/groups, event listeners, event subscribers, factories, and seeders
  that previously defined `bootPackage*` methods on the Package but were
  never invoked.
- `docs/COMMANDS.md` — Artisan command reference.
- ADR-0011 documents the six collision-by-design unwired traits.
- `scripts/init.sh` — single bash bootstrap (ADR-007).

### Fixed
- `PatternResolver` and `PackageAnalyzer` now implement the missing
  `canResolve()` / `getReport()` methods from their interfaces (would have
  crashed on instantiation).
- `InstallCommand` now imports `Package` (was triggering class-not-found
  errors at static-analysis time).
- `PathResolver` error message no longer references undefined `$className`.
- Four `glob()` callsites now guarded with `?: []` — `glob` returns `false`
  on failure, which broke the subsequent `foreach`.
- `IsolatedTestCase::assertCommandExists` guards `$this->app` for null.
- `ValidClassNameRule` closure docblock matches Laravel's actual signature
  (`Closure(string, string|null=): PotentiallyTranslatedString`).
- SBOM `--output` paths that would escape `$projectRoot` are now refused.
- 16 `/** @test */` doc-comments in `PathResolverIntegrationTest` migrated to
  `#[\PHPUnit\Framework\Attributes\Test]` (PHPUnit 12 compatibility).

### Security
- `FacadeAutoGenerator` validates AsFacade alias/accessor/namespace against
  PHP-identifier regex; distinguishes class-string from container-binding-key
  accessors. Closes the code-injection seam in the generator.
- `OsvAuditService` strips ANSI/control characters from network-supplied
  advisory text; `rawurlencode`s OSV ids; bounds-checks timeout (1–600s).
- All three new services route filesystem I/O through `File::`; HTTP through
  `Http::`. `Str::*` / `Arr::*` replace raw `str_*` / `array_*` accesses.

### Targets
- PHP `^8.3 || ^8.4`
- Laravel `^13.0`
- Pest `^3.0`, Testbench `^11.0`, Larastan `^3.0`
