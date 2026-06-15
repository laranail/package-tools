# Contributing

Thank you for your interest in `laranail/package-tools`.

## Quick start

```bash
git clone https://github.com/laranail/package-tools.git
cd package-tools
bash scripts/init.sh
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

- PHP `^8.3` (we recommend 8.4+ features where they materially shrink code).
- `declare(strict_types=1);` on every PHP file.
- `#[\Override]` on every overriding method.
- Pint is the sole formatter (see `pint.json`).
- PHPStan level 8 (see `phpstan.neon`); `composer lint` must be clean.
- Rector dry-run must be clean (see `rector.php`).

## Architecture

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for the runtime
overview, [docs/adr/](docs/adr/) for accepted architectural decisions,
and the suite master plan at `.plans/CLEANUP-MASTER-PLAN.md` (lives in
`laranail/package-scaffolder`) for cross-package context.

## Code of conduct

By contributing, you agree to abide by the project's
[Code of Conduct](CODE_OF_CONDUCT.md).
