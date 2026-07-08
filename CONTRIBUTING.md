# Contributing

Thank you for your interest in `laranail/package-tools`.

## Quick start

```bash
git clone https://github.com/laranail/package-tools.git
cd package-tools
bash .scripts/init.sh
composer test
```

## Development workflow

1. Branch off `main`.
2. Write tests first when adding/changing behaviour. We aim for ≥80% line coverage on new code.
3. Run the full local check before opening a PR:
   ```bash
   composer lint     # pint + phpstan + rector --dry-run
   composer test     # vendor/bin/pest
   composer audit    # composer audit (security)
   ```
4. Use [Conventional Commits](https://www.conventionalcommits.org/) — the release workflow regenerates `CHANGELOG.md` from them.
5. Open the PR against `main`. CI must pass before merge.

## Coding standards

- PHP `^8.3` (8.3, 8.4, 8.5 supported). Don't gate on 8.4/8.5-only syntax — Rector is pinned to `php83`. CI runs on 8.5.
- `declare(strict_types=1);` on every PHP file.
- `#[\Override]` on every overriding method.
- Pint is the sole formatter (see `pint.json`).
- PHPStan level 8 (see `phpstan.neon`); `composer lint` must be clean.
- Rector dry-run must be clean (see `rector.php`).

## Artisan command naming

Commands across the laranail family follow one shape:

```
laranail::<package-slug>.<command>
```

- `laranail` — the org namespace.
- `::` — the namespace separator.
- `<package-slug>` — the composer slug suffix, so the source package is
  unambiguous: `package-tools`, `package-scaffolder`, `db-tools`, …
- `.<command>` — the command itself, after a dot.

Examples: `laranail::package-tools.doctor`, `laranail::package-tools.sbom`,
`laranail::package-scaffolder.new`.

Symfony Console's `Command::validateName()` rejects `::` by default. Extend
`Simtabi\Laranail\Package\Tools\Commands\Command` (or `use` the
`Commands\Concerns\SupportsNamespacedNames` trait) on your command — it writes
the name past that check, so both `::` and `:` are accepted and the command
still dispatches. The four built-in commands use this base.

## Architecture

See [docs/architecture.md](docs/architecture.md) for the runtime
overview.

## Code of conduct

By contributing, you agree to abide by the project's
[Code of Conduct](CODE_OF_CONDUCT.md).
