# ADR-0004 — Trait composition: aggregator pattern

- **Status:** Accepted (2026-05)
- **Scope:** `package-tools` (and any consumer that builds on its trait library)

## Context

`Concerns/Package/` contains 51 trait files. PHP supports
`insteadof`/`as` for trait method conflicts but the resulting
multi-trait `use {…}` block becomes unreadable past ~10 traits and
leaks resolution into every consumer.

## Decision

Group traits by domain into **aggregator** traits
(`ConfiguresConfig`, `ConfiguresViews`, `ConfiguresAssets`, …). The
`Package` builder and `PackageServiceProvider` `use` only
aggregators — never raw leaf traits.

Rules:

1. Each aggregator wraps **at most ~10** leaf traits.
2. First-party trait families use **unique-by-prefix** naming
   (`hasX`, `usesX`, `processX`) so collisions don't happen in normal
   composition.
3. `insteadof`/`as` is reserved for genuine third-party trait
   collisions. Resolution always lives at the aggregator level, never
   on `Package` itself.
4. Method-name conflicts are surfaced by Reflection at trait-composition
   time and resolved during the aggregator commit (with a Phase 3-style
   audit).

## Consequences

- `Package` `use`s ~12 aggregators instead of 25–51 raw traits.
- New leaf traits land in the appropriate domain aggregator without
  changing `Package`'s import list.
- The 26 currently-unused leaf traits can be wired in batches as their
  conflicts are resolved (Phase 3b backlog item) without disturbing the
  active set.
