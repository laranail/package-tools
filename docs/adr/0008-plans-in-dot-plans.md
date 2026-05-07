# ADR-0008 — Plans live in `.plans/`

- **Status:** Accepted (2026-05)
- **Scope:** Suite-wide

## Context

Long-lived planning documents have lived in various places: a visible
`PROJECT_PLANS/` directory (since archived), a brief `plans/` interlude,
and the hidden `.plans/`. The hidden directory was deliberately
designed (not an accident) and is the user-blessed canonical location.

## Decision

Long-lived planning artifacts live in **`.plans/`** (hidden) at the
repo root. Each repo's `.plans/` is independent — no cross-repo
expectations.

`.plans/CLEANUP-MASTER-PLAN.md` in `laranail/package-scaffolder` is the
canonical multi-revision plan that drove the suite cleanup (v1 → v9).

`.plans/reference/` is reserved for cross-cutting reference docs
(version matrices, naming conventions, etc.) but is empty by default
and removed when not in use.

ADRs are **not** plans — they live at `docs/adr/NNNN-*.md` per repo
and are immutable once accepted.

The visible `plans/` directory is **never** used.

## Consequences

- `.plans/CLEANUP-MASTER-PLAN.md` becomes the "how did we get here"
  history file for the suite.
- New planning work that doesn't fit into an ADR (e.g., a tactical
  spike, a multi-week migration plan) lands in `.plans/` of the
  appropriate repo with a date-prefixed filename.
- Pre-v9 planning artifacts (the original 53-file `PROJECT_PLANS/`)
  archived to `.artifacts/legacy-plans-<date>/` per Phase 12.
