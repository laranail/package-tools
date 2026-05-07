# Architectural Decision Records (ADRs)

This directory holds the accepted architectural decisions for the
`laranail/*` package suite. Each file follows
[Michael Nygard's template](https://adr.github.io/madr/).

## Lifecycle

ADRs move through `proposed → accepted → superseded`. Once **accepted**,
an ADR is never edited; if a decision changes, a new ADR is created
that supersedes the old one (with a `Superseded by: NNNN` link added to
the old).

## Index

| ID | Title | Status |
|---|---|---|
| [0001](0001-five-packages.md) | Five packages, not one | Accepted |
| [0002](0002-polyrepo.md) | Polyrepo, no monorepo | Accepted |
| [0003](0003-php-83-laravel-13.md) | Targets: PHP 8.3+, Laravel 13+ | Accepted |
| [0004](0004-trait-aggregator-pattern.md) | Trait composition: aggregator pattern | Accepted |
| [0005](0005-fluent-return-convention.md) | Fluent return: `$this` for chaining, `static` for terminal | Accepted |
| [0006](0006-env-append-only.md) | Configuration: JSON for shape, Laravel `.env` for secrets, append-only writes | Accepted |
| [0007](0007-bash-banished.md) | Bash banished except for `init.sh` | Accepted |
| [0008](0008-plans-in-dot-plans.md) | Plans live in `.plans/` | Accepted |
| [0009](0009-attribute-discovery.md) | Attribute-driven discovery is the differentiator vs Spatie | Accepted |
| [0010](0010-database-tools-independent.md) | `database-tools` is genuinely independent | Accepted |
| [0011](0011-deferred-trait-wiring.md) | Defer six leaf traits with collision-by-design | Accepted |
| [0012](0012-package-seeder-auto-run.md) | Package-author seeder plumbing lives in `package-tools` | Accepted |

## Proposing a new ADR

Copy [template.md](template.md) to `NNNN-short-kebab-title.md` where
`NNNN` is the next free number (suite-wide ADRs here start at 0001;
per-package ADRs in other repos start at 0100). Fill in every section;
open a PR titled `docs(adr): NNNN — <title>`. Once accepted and
merged, the ADR is immutable — supersession is via a new ADR.

## Sister-package ADR directories

`package-scaffolder/docs/adr/` and `database-tools/docs/adr/` each
contain a `README.md` index that cross-references the canonical ADRs
above (single source of truth). Per-package ADRs (specific to a single
repo) live in their own repo's `docs/adr/` and are numbered from `0100`
upward.
