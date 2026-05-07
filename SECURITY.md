# Security

## Supported versions

| Version | Status         |
|---------|----------------|
| 1.x     | Active support |

Pre-release builds (`v1.0.0-beta.*`) receive security fixes only on the
latest tag.

## Reporting a vulnerability

Please **do not** open a public GitHub issue for security-sensitive
findings. Instead, email **hello@simtabi.com** with:

- A description of the vulnerability and its impact.
- Steps to reproduce (proof-of-concept welcome).
- The affected version(s).

We aim to acknowledge reports within 72 hours and triage within 5
business days. Coordinated disclosure timelines are negotiated per case.

## Supply-chain posture

- `roave/security-advisories` (dev-master) is in `require-dev` —
  composer install fails if any registered package has an open advisory.
- Weekly OSV.dev scan against `composer.lock` runs in CI
  (`.github/workflows/security.yml` → `laranail/.github/.github/workflows/security.yml`).
- CycloneDX SBOM is emitted as a release artifact for every tagged
  release (`release.yml`).
- Dependabot updates `composer` + `github-actions` weekly with
  Conventional Commits prefixes.
