# laranail::package-tools.sbom

CycloneDX SBOM generator. Reads the host project's `composer.json` + `composer.lock` and emits a
CycloneDX 1.5 JSON Software Bill of Materials. Pure-PHP — every byte is
computed in-process, with no shelling out, so the output is reproducible
and CI-friendly. Backed by
`Simtabi\Laranail\Package\Tools\Services\Sbom\SbomGenerator`.

```bash
php artisan laranail::package-tools.sbom [--output=sbom.json] [--print]
```

| Flag | Meaning |
|---|---|
| `--output=PATH` | Write to `PATH` (relative to the project root, or absolute). Default: `sbom.json`. |
| `--print` | Emit the JSON to stdout instead of writing a file. |

On success the command prints `CycloneDX SBOM written to: <path>` (or the
JSON, with `--print`) and exits `SUCCESS`. Any `Throwable` is caught,
reported as `SBOM generation failed: <message>`, and the command exits
`FAILURE` — for example when `composer.lock` is missing (`Run composer
install first.`).

## Sample run

```bash
# Write the default sbom.json at the project root.
$ php artisan laranail::package-tools.sbom
CycloneDX SBOM written to: /app/sbom.json

# Pipe to stdout and slice it with jq.
$ php artisan laranail::package-tools.sbom --print | jq '.components | length'
118

# Write under a (created-on-demand) build directory.
$ php artisan laranail::package-tools.sbom --output=build/sbom/project.cdx.json
CycloneDX SBOM written to: /app/build/sbom/project.cdx.json
```

The generator is also usable directly —
`new SbomGenerator(base_path())` exposes `generate(): array` (the BOM as
a PHP array) and `generateToFile(string $outputPath): string` (returns
the absolute path written).

## Output

The document conforms to the
[CycloneDX 1.5 JSON spec](https://cyclonedx.org/docs/1.5/json/):

- `bomFormat: "CycloneDX"`, `specVersion: "1.5"`, `version: 1`.
- `serialNumber` — a fresh RFC 4122 v4 UUID per run
  (`urn:uuid:…`, cryptographically random via `random_bytes`).
- `metadata.timestamp` — UTC, `gmdate('Y-m-d\TH:i:s\Z')`.
- `metadata.tools` — `{ vendor: "simtabi", name: "laranail/package-tools", version }`.
- `metadata.component` — the root project as an `application` component.
- `components` — one entry per locked package.

Each component carries `type`, `bom-ref`, `name`, `version`, and a
Composer `purl` (`pkg:composer/<name>@<version>`). A leading `v` is
stripped from versions. `description`, `externalReferences` (a `website`
entry from `homepage`), and `licenses` are included when present.
Packages from `packages-dev` are emitted with `scope: "optional"`.

## Path safety

The output path is clamped to within the project root. The candidate
path is lexically normalised (collapsing `.` and `..` segments without
touching the filesystem); if the result is not the project root or a
descendant of it, generation throws a `RuntimeException` (`SBOM output
path is outside project root`). Missing parent directories of a valid
output path are created (`0755`). To write outside the project tree, run
a separate copy step.

## See also

- [audit.md](audit.md) — the companion supply-chain command; the release
  workflow generates an SBOM and runs an audit together.

The `release.yml` workflow attaches a CycloneDX SBOM to every GitHub
release.

[← Docs index](../../README.md#documentation)
