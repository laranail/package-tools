# ADR-0003 — Targets: PHP 8.3+, Laravel 13+

- **Status:** Accepted (2026-05)
- **Scope:** Suite-wide

## Context

Laravel 13 shipped 2026-03-17 with zero breaking changes from 12 and a
PHP 8.3 floor. The original `laranail/packager` codebase declared
`php: >=8.0` and `illuminate/contracts: ^9.28|^10.0|^11.0|^12.0`.
Carrying compat for 4 Laravel majors costs more than it earns for a
fresh package suite that hasn't shipped a v1.

## Decision

Floor: PHP `^8.3`, Laravel `^13.0`. Supported: PHP 8.3, 8.4, 8.5.
Recommend PHP 8.4+ features (property hooks, asymmetric visibility)
opportunistically where they materially shrink code, but never gate
behind 8.4+ features without a parallel 8.3 path. `#[\Override]`
attribute mandatory on every overriding method.

Test stack: Pest `^3.0`, Testbench `^11.0`, Larastan `^3.0`.

## Consequences

- Smaller compat surface; faster onboarding.
- Some prospective consumers stuck on Laravel 11/12 will need to wait
  for an LTS branch — defer the `1.x-laravel11` branch only if a
  concrete request surfaces.
- `laranail/laranail` (which has `^10.0 || ^11.0 || ^12.0` today) gets
  bumped to `^13.0` per the suite-wide direction; cut a frozen
  `1.x-laravel12` LTS branch first.
