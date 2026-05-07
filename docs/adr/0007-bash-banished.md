# ADR-0007 — Tooling is pure PHP/Composer; bash banished except for `init.sh`

- **Status:** Accepted (2026-05). Supersedes the earlier "Python tooling" stance.
- **Scope:** Suite-wide

## Context

An earlier revision proposed a Python CLI package
(`laranail/package-scaffolder-python`) with a `lib/` of OOP base classes
covering config, codemod, audit, runner. Re-examination showed:

1. Most of the original `/scripts/` were one-shot codemods that had
   already finished their work (the Packager → Package + Generator →
   Scaffolder restructure).
2. The remainder were thin wrappers around tools that already have
   first-class PHP/Composer interfaces (Pest, PHPStan, Pint, Rector,
   `composer audit`).
3. Adding Python forces every contributor to maintain a second
   toolchain.

## Decision

Tooling is **pure PHP/Composer**. The contributor-facing surface is
Composer script aliases (`composer setup` / `test` / `lint` / `audit`).

**No `.sh` file may exist anywhere in any laranail/* repo except
`scripts/init.sh`.** Each repo's `init.sh` is ~75 lines of bash
verifying php≥8.3 + composer, running `composer install`, discovering
the host `.env`, smoke-checking Pint, and printing a summary.

CI gate: `find . -type f -name "*.sh" -not -path "*/vendor/*"` must
return exactly one path: `./scripts/init.sh`. The
`bash-elimination-gate` job in `laranail/.github/static-analysis.yml`
fails the build otherwise.

For codemods, use Rector. For one-off shell tasks during a phased
cleanup, write a brief PHP one-shot and leave it under `.artifacts/`
(gitignored), not under `scripts/`.

## Consequences

- Smaller cognitive surface for PHP-only contributors.
- `package:doctor`, `package:audit`, `package:sbom`, `package:ide-helper`
  ship as native Laravel Artisan commands in `package-tools` — more
  idiomatic since they need to inspect an installed Laravel app's
  container anyway.
- ~16 hours dropped from the original suite estimate by eliminating
  the Python package + its CI/test surface.
- Cross-cutting suite checks (rare; quarterly) handled by a small
  `gh`-based bash snippet in `laranail/.github`, not a standalone tool.
