# Release process

`laranail/package-tools` is released **tag-driven**: pushing a `vX.Y.Z` tag triggers the release workflow, which publishes the GitHub Release (with the `package-tools.cdx.json` CycloneDX SBOM attached) and Packagist updates from the tag automatically.

## Versioning & stability

The package follows [Semantic Versioning](https://semver.org). Bug fixes are a patch (`x.y.Z`), backward-compatible features a minor (`x.Y.0`), breaking changes a major (`X.0.0`) — 3.0's seeder redesign is the canonical example. The PHP floor (`^8.4.1 || ^8.5`) and illuminate/laranail constraints live in `composer.json`.

**What SemVer covers (the public API):**

- The `Package` builder's fluent surface (every `Has*`/`Configures*` method) and the abstract `PackageServiceProvider` with its five lifecycle hooks.
- The fluent definitions under `Support\Definitions\*` (`AutoSeederDefinition`, `DoctorCheckDefinition`, `InstallCommandDefinition`, `LogDefinition`, `ScheduledCommandDefinition`), the shipped enums, the `PackageSeeder` facade, the `PackageLogger`, the events, and the shipped Artisan commands (`laranail::package-tools.*`).
- The `config/package-tools.php` key shapes and the documented host-override config namespaces (`{vendor}.{package}.*`).

**What is NOT covered:**

- Anything marked `@internal`, the `Services\*` internals' constructor signatures, and test fixtures/helpers not under `Testing\*`.

## Cutting a release

1. Land everything on `main` with `composer lint` (pint + phpstan + rector) and `composer test` green — CI runs the 8.4/8.5 matrix, static analysis, and the security audit on every push.
2. Add the `## [X.Y.Z]` block to `CHANGELOG.md` (Keep a Changelog) — and for breaking releases the matching `UPGRADING.md` section.
3. Commit, push, wait for CI green.
4. Tag and release; the release body is the CHANGELOG block (never a bare stub):

   ```bash
   git tag vX.Y.Z && git push origin vX.Y.Z
   gh release create vX.Y.Z --title "vX.Y.Z" --notes-file <(awk '/^## \[X.Y.Z\]/{f=1;next} /^## \[/{f=0} f' CHANGELOG.md) --generate-notes
   ```

5. Packagist syncs from the tag automatically; verify with `composer show laranail/package-tools --available`.

## Consumers to reconcile after a breaking release

The family that pins this package: `laranail/toolkit`, `laranail/package-scaffolder` (require, require-dev, AND its Laravel blueprint stub), the `laranail/{stripe,otp,geoip,whatsapp,paystack,sms,paypal}` integrations, and `ichava/core` (which fans out to the icon packs via `ichava/core`). Bump their constraints and run their suites before tagging them.

---

[← Docs index](../README.md#documentation)
