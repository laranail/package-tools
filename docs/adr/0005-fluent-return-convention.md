# ADR-0005 — Fluent return convention

- **Status:** Accepted (2026-05)
- **Scope:** `package-tools` (and downstream packages built on it)

## Context

The legacy codebase mixed `return $this` and `return static` across
fluent methods. Both work; both convey slightly different type
information at the IDE/typechecker level.

## Decision

- **Chaining methods** (those that participate in a fluent builder
  chain like `$package->name()->hasViews()->hasMigration()`) return
  **`$this`**. Simpler, matches Spatie's convention.
- **Terminal / factory methods** (those that don't participate in
  chaining — typically constructors or static factories) return
  **`static`** to allow late-static-binding through subclasses.
- **Methods that don't return the instance** return their concrete
  type or `void`.

## Consequences

- IDE auto-completion stays accurate across long chains.
- Subclasses can refine the chain return type by overriding without
  losing their own type identity (PHP 8.0+ `static` covariance).
- Migration: an automated codemod (Rector or hand-written) normalises
  existing code; PHPStan level 8 + Pint catch drift in PRs.
