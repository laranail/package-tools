# ADR-0011 — Defer six leaf traits with collision-by-design

- **Status:** Accepted
- **Date:** 2026-05-04
- **Deciders:** Maintainer
- **Scope:** `package-tools` — `src/Concerns/Package/`

## Context

Phase 3 wired 25 of 51 leaf traits into 12 domain aggregators (ADR-0004).
Phase 3b widened the surface to 44 leaves across 13 aggregators by adding 19
non-conflicting leaves (HasAssetCleanup, HasVueAssets, HasProgressIndicators,
HasComponentNamespaces, HasEnhancedAnonymousComponents,
HasSafeComponentRegistration, HasViewComponentLoader, HasVueComponents,
HasAdditionalNamespaceFormats, HasConfigManipulation, HasGlobalConfigMerging,
HasNestedConfigFiles, DiscoversWithAttributes, HasBatchResourceLoading,
HasDoctorChecks, HasEnhancedValidation, HasEnhancedMiddleware, HasNestedLevels,
HasComposerOperations) plus a new `ConfiguresComposer` aggregator.

Six leaves remain unwired because each duplicates a method or property that an
already-wired sibling defines. PHP traits cannot be unioned silently when their
member names collide — composing them forces an `insteadof`/`as` choice that
implicitly deprecates one implementation. We chose to make that choice
explicitly here rather than encode it in trait DSL.

| Leaf | Collides with | Member |
|---|---|---|
| `HasCachedNamespaces` | `HasConfigNamespace` | `getDottedNamespace`, `getDashedNamespace`, `getDoubleColonNamespace`, `getSlashNamespace` |
| `HasModuleAssets` | `HasAssetPublisher` | `publishModuleAssets` |
| `HasAssetGroups` | `HasAssetPublisher` | `getAssetGroups`, `$assetGroups` |
| `HasEventSystem` | `HasMiddlewareManagement` | `registerEventListener`, `registerEventSubscriber` |
| `HasViewComposerRegistry` | `HasEnhancedViewComposers` | `registerViewComposer` |
| `ManagesComposer` | `HasComposerOperations` | `composerRequire`, `composerRemove`, `composerDumpAutoload` |

## Decision

Leave the six traits in `src/Concerns/Package/` as code, but do not aggregate
them into any `Configures*`. They remain reachable via direct `use` inside a
consumer Package subclass, which is the deliberate escape hatch for the rare
package that wants the alternative behaviour (e.g. caching namespace formats,
or scaffolder-style composer manipulation).

`ManagesComposer` additionally belongs to the scaffolder runtime and will
relocate to `package-scaffolder/src/Concerns/Package/` in a separate change.

## Consequences

- **Wins**: zero method-collision pressure on consumers; no `insteadof`
  resolution buried in aggregators; the wired surface is self-consistent.
- **Costs**: 6 traits are documented dead weight in this repo until they are
  consolidated, refactored as decorators, or deleted.
- **Migration**: existing code that did `use HasModuleAssets` directly still
  works. New packages should reach for the wired aggregator first.
- **Reversibility**: trivial — flip a `use` line in the relevant aggregator
  once the collision is resolved (rename, decorator, or deletion).

## Alternatives considered

- **Wire with `insteadof`**: Aggregator picks one implementation, the other is
  shadowed. Rejected — silently picks a winner without surfacing the choice.
- **Refactor each pair into a decorator**: The "cached" / "enhanced" variants
  wrap the canonical one. Defensible but not yet justified by usage; revisit
  when a consumer asks for it.
- **Delete the unwired traits outright**: Saves bytes but loses the option
  value of the alternative implementations. Cheap to keep, expensive to
  rewrite.
