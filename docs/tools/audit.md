# laranail::package-tools.audit

Vulnerability audit. Posts every `name@version` pair from `composer.lock` to OSV.dev's
[`/v1/querybatch`](https://google.github.io/osv.dev/post-v1-querybatch/)
endpoint and surfaces the advisories returned. Exits non-zero when at
least one package is affected. Backed by
`Simtabi\Laranail\Package\Tools\Services\Audit\OsvAuditService`.

```bash
php artisan laranail::package-tools.audit [--no-dev] [--json] [--timeout=30]
```

| Flag | Meaning |
|---|---|
| `--no-dev` | Skip `packages-dev` (audit production deps only). |
| `--json` | Emit machine-readable JSON instead of the TTY view. |
| `--timeout=N` | HTTP timeout for OSV.dev requests, in seconds. Default: 30. Must be 1–600, else a `RuntimeException` is thrown. |

## Flow

1. `composer.lock` is read from the project root (missing lockfile →
   `Audit failed: composer.lock not found …`, exit `FAILURE`).
2. Each entry from `packages` (and `packages-dev` unless `--no-dev`) is
   collected as `{name, version}`; a leading `v` is stripped from
   versions.
3. A single batch request is posted to
   `https://api.osv.dev/v1/querybatch` with each query keyed by package
   name and the `Packagist` ecosystem. A non-2xx response or a body
   missing the `results` array throws.
4. For every package whose result carries `vulns`, an advisory is
   recorded with the vulnerability `id`, `summary`, `severity` (the
   first `severity[].score`, or `null`), and a `url`
   (`https://api.osv.dev/v1/vulns/<id>` with the id URL-encoded).

The command exits `FAILURE` when `vulnerable_count > 0`, otherwise
`SUCCESS`.

## Output hardening

Network-supplied advisory text is sanitised before being printed: ANSI
escape sequences and other control characters are stripped, so a hostile
advisory cannot rewrite the operator's terminal scrollback or spoof a
prompt. OSV ids are URL-encoded before being assembled into the advisory
link.

## JSON output shape

```json
{
  "scanned": 42,
  "vulnerable_count": 1,
  "advisories": [
    {
      "package": "vendor/name",
      "version": "1.2.3",
      "vulns": [
        {
          "id": "GHSA-xxxx-xxxx-xxxx",
          "summary": "…",
          "severity": "7.5",
          "url": "https://api.osv.dev/v1/vulns/GHSA-xxxx-xxxx-xxxx"
        }
      ]
    }
  ]
}
```

When no packages are locked, the report is `{ scanned: 0,
vulnerable_count: 0, advisories: [] }`.

## Sample run

```bash
# Clean tree — exit 0.
$ php artisan laranail::package-tools.audit

laranail::package-tools.audit — scanned 118 packages

No known vulnerabilities found.

# Production deps only, machine-readable, in CI.
$ php artisan laranail::package-tools.audit --no-dev --json
{
  "scanned": 74,
  "vulnerable_count": 0,
  "advisories": []
}
```

When advisories are returned the command exits non-zero and lists each
affected package with its vulnerability id, severity, summary, and OSV
link, making it usable directly as a CI gate.

## See also

- [sbom.md](sbom.md) — the companion supply-chain command (CycloneDX
  inventory of the same lockfile).

The `security.yml` workflow runs this audit (and
`google/osv-scanner-action`) on a weekly schedule.

[← Docs index](../../README.md#documentation)
