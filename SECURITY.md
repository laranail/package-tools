# Security

## Supported versions

| Version  | Status               |
|----------|----------------------|
| 0.5.x    | Active support       |
| < 0.5.0  | Security fixes only  |

Security fixes are released on the latest `0.x` tag.

## Reporting a vulnerability

Please **do not** open a public GitHub issue for security-sensitive
findings. Instead, email **opensource@simtabi.com** with:

- A description of the vulnerability and its impact.
- Steps to reproduce (proof-of-concept welcome).
- The affected version(s).

We aim to acknowledge reports within 72 hours and triage within 5
business days. Coordinated disclosure timelines are negotiated per case.

## Supply-chain posture

- `roave/security-advisories` (dev-latest) is in `require-dev` —
  composer install fails if any registered package has an open advisory.
- Weekly `composer audit` (GitHub/Packagist advisory database) runs in CI
  (`.github/workflows/security.yml`), failing the build on any advisory. The
  package also ships `php artisan laranail::package-tools.audit` for on-demand
  OSV.dev scanning of a host app's `composer.lock`.
- CycloneDX SBOM is emitted as a release artifact for every tagged
  release (`release.yml`).
- Dependabot updates `composer` + `github-actions` weekly with
  Conventional Commits prefixes.
