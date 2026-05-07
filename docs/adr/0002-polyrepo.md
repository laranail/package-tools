# ADR-0002 — Polyrepo, no monorepo

- **Status:** Accepted (2026-05)
- **Scope:** Suite-wide

## Context

After ADR-0001, the question is how to host four packages: monorepo
(single git repo, subtree-split publishing via
`symplify/monorepo-builder`) or polyrepo (one git repo per package).

## Decision

Polyrepo. Each package gets its own independent git repo at
`/Users/imanimanyara/Artisan/projects/opensource/laranail/<package-name>/`.
No monorepo, no subtree-split, no `symplify/monorepo-builder`.

Cross-cutting concerns are handled by:

- `laranail/.github/.github/workflows/*.yml` — **reusable** GitHub
  Actions workflows. Each package's CI is a thin caller (10–15 lines)
  invoking these via `uses: laranail/.github/.github/workflows/<name>.yml@main`.
- A single GitHub issue cross-linked from per-repo PRs for any change
  that touches multiple packages.

## Consequences

- Each repo self-contained: own CI runs, own CHANGELOG, own version branches.
- Per-package Packagist visibility, independent SemVer.
- Cross-cutting changes are slower (multi-PR minimum) but each repo is
  mentally simpler — a contributor can land work in `database-tools`
  without thinking about the other three.
- Reusable workflows in `laranail/.github` keep CI duplication low; a
  CI shape change lands in one PR and propagates on next workflow run.
- No monorepo build orchestrator to maintain.
