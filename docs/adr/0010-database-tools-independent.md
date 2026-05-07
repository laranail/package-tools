# ADR-0010 — `database-tools` is genuinely independent

- **Status:** Accepted (2026-05)
- **Scope:** `database-tools`, with implications for `package-tools`

## Context

The cleanup surfaced general-purpose Laravel DB utilities (UUID/NanoID/
ULID model traits, audit observers, soft-delete-with-undo, JSON column
accessors, schema macros) that don't belong in the package-builder
runtime. Two homes were considered: bundle them into `package-tools`,
or carve a separate `laranail/database-tools` package.

## Decision

Carve `laranail/database-tools` as a **fully independent** package.

Scope:

- General-purpose Laravel DB utilities — model traits, schema macros
  (`auditColumns()`, `softDeletesWithUndo()`), observer base classes
  (`AuditObserver`), eager-load helpers, cursor-pagination DTO.

Out of scope:

- The package-builder's `HasMigrations`, `HasFactoriesAndSeeders`,
  `ProcessMigrations` traits — those are coupled to the `Package`
  builder API and stay in `package-tools`.

Dependencies:

- `database-tools` requires only `illuminate/database` + `illuminate/support`.
- `database-tools` does **not** require `package-tools`.
- `package-tools` does **not** require `database-tools`.
- Optional integration trait `InteractsWithDatabaseTools` lives in
  `package-tools` (not the reverse) — package authors opt in.

## Consequences

- Two genuinely small packages instead of one bloated one.
- `database-tools` is usable by any Laravel app, not just package
  authors. Wider potential audience.
- A fix to `HasUuid` in `database-tools` doesn't trigger a release of
  `package-tools` (different SemVer cadences).
- The "no `package-tools` dep" invariant is verified at install time
  by Composer; any future contribution that violates it fails CI.
