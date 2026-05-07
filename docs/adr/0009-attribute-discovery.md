# ADR-0009 — Attribute-driven discovery is the differentiator vs Spatie

- **Status:** Accepted (2026-05)
- **Scope:** `package-tools`

## Context

`spatie/laravel-package-tools` (the canonical inspiration for our
runtime) registers commands, routes, view composers, and facades via
explicit fluent steps: `hasCommand(FooCommand::class)`,
`hasRoute('web')`, `hasViewComposer('layout', AppComposer::class)`.

Laravel 13 added attribute-based registration as a first-party pattern
(`#[Boot]`, `#[Initialize]`, `#[Scope]`, `#[ScopedBy]` on Eloquent).
The ecosystem (`spatie/laravel-auto-discoverer`) supports
attribute-driven discovery as a primitive. Spatie's
`laravel-package-tools` does not yet expose this.

## Decision

`package-tools` ships **first-party attributes** plus a fluent-step
opt-in:

- `#[AsArtisanCommand(signature, description)]` — TARGET_CLASS.
- `#[AsRoute(method, uri, name?, middleware?)]` — TARGET_CLASS, IS_REPEATABLE.
- `#[AsFacade(alias, accessor?)]` — TARGET_CLASS.
- `#[AsViewComposer(views: string|string[])]` — TARGET_CLASS.
- `Package::discoversWithAttributes(?dir, ?ns)` — opt-in fluent step.
  At packageBooted() time, scans `$dir` (default `packageBasePath('src')`)
  for classes carrying the four attributes and registers them via the
  existing fluent API (`hasCommand()`, route loader, `hasViewComposer()`).

Spatie's path-based fluent steps remain as the explicit fallback —
`discoversWithAttributes()` is additive, never required.

`AttributeDiscoverer` (the underlying service) is stdlib-only —
`RecursiveIteratorIterator` + `ReflectionClass`. No
`spatie/laravel-auto-discoverer` dependency.

`#[AsFacade]` discovery is wired in v1.2 (Tier C); the other three
attributes ship in v1.0 (Tier A).

## Consequences

- The marketing tagline differentiates `package-tools` from Spatie's
  offering and aligns with Laravel 13's direction.
- `package:doctor` (ADR-009 sibling feature) validates that attributes
  resolved as expected.
- Some boilerplate (`hasCommand(FooCommand::class)` × 12) collapses to
  a single `discoversWithAttributes()` call.
