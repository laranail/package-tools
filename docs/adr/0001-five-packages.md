# ADR-0001 — Five packages, not one

- **Status:** Accepted (2026-05)
- **Deciders:** Suite maintainers
- **Scope:** Suite-wide

## Context

The original `laranail/packager` repo bundled four concerns in one
codebase: a runtime library, an Artisan generator, dev tooling, and 139
stubs. Different audiences, different release cadences, different
stability contracts. Bundling caused a Scaffolder bugfix to require a
runtime release, and shipped 139 stubs into every runtime consumer's
vendor folder.

## Decision

Split into four (originally five — see ADR-007 for the Python drop)
purpose-built Composer packages:

1. `laranail/laranail` — utility toolbox (existing, untouched).
2. `laranail/package-tools` — runtime base library.
3. `laranail/package-scaffolder` — Artisan generator + 139 stubs.
4. `laranail/database-tools` — independent Laravel DB utilities.

Plus two non-Composer support repos:

- `laranail/.github` — reusable GitHub Actions workflows.
- `laranail/documentation` — VitePress documentation site source.

Dependency direction: `package-scaffolder → package-tools`.
`database-tools` and `laranail/laranail` are independent.

## Consequences

- Each package versions independently on Packagist.
- `package-tools` receives SemVer-major discipline; `package-scaffolder`
  can iterate freely.
- Generated packages no longer ship the scaffolder code as a transitive
  dependency.
- Cross-cutting refactors require multi-PR coordination — mitigated
  by ADR-002 (reusable workflows) and a lightweight cross-repo issue
  protocol.
