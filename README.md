# laranail/package-tools

[![Latest version on Packagist](https://img.shields.io/packagist/v/laranail/package-tools.svg)](https://packagist.org/packages/laranail/package-tools)
[![Tests](https://github.com/laranail/package-tools/actions/workflows/tests.yml/badge.svg)](https://github.com/laranail/package-tools/actions/workflows/tests.yml)
[![Static analysis](https://github.com/laranail/package-tools/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/laranail/package-tools/actions/workflows/static-analysis.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

> Runtime base library for building Laravel packages — a fluent `Package` builder and an abstract `PackageServiceProvider` (in the spirit of `spatie/laravel-package-tools`), plus attribute-driven discovery, declarative + array-batch registration helpers, `package-tools.*` Artisan commands, abstract HTTP controllers, and a testing harness.

Requires PHP `^8.4.1 || ^8.5` on Laravel `^13`.

## Install

```bash
composer require laranail/package-tools
```

## <a name="documentation"></a>Documentation

Hosted at **[opensource.simtabi.com/documentation/laranail/package-tools](https://opensource.simtabi.com/documentation/laranail/package-tools/)**.

### Guides

- [Installation](docs/installation.md) — requirements and install
- [Getting started](docs/getting-started.md) — the smallest working provider
- [Configuration](docs/configuration.md) — what the toolkit reads and how to change it
- [Architecture](docs/architecture.md) — the Package/provider split, and why the seams are where they are
- [Services](docs/services.md) — the service layer
- [Seeding](docs/seeding.md) — db:seed-time bundles, autorun and scheduled execution
- [Failure handling](docs/failure-handling.md) — classify by consequence: Critical fails fast, Degradable continues
- [Release](docs/release.md) — the release process

### Reference

- [About sections](docs/tools/about-sections.md) — fluent `php artisan about` sections
- [Action events](docs/tools/action-events.md) — the `PackageAction{Started,Succeeded,Failed}` lifecycle events
- [Attribute discovery](docs/tools/attribute-discovery.md) — registering commands, routes and listeners by attribute
- [Audit](docs/tools/audit.md) — the package audit command
- [Command naming](docs/tools/command-naming.md) — the `vendor::slug.command` shape, and why `::` needs a base class
- [Config manager](docs/tools/config-manager.md) — the fluent runtime config manager
- [Config namespacing](docs/tools/config-namespacing.md) — how a config key is derived, and the id-versus-key distinction
- [Container](docs/tools/container.md) — declaring singletons, facades and class aliases
- [Dist integrity](docs/tools/dist-integrity.md) — every path `composer.json` references must survive `git archive`
- [Doctor](docs/tools/doctor.md) — health checks, and classifying them by consequence
- [Http controllers](docs/tools/http-controllers.md) — the controller base and `#[AsRoute]`
- [Ide helper](docs/tools/ide-helper.md) — generated IDE metadata
- [Isolated testcase](docs/tools/isolated-testcase.md) — the testing harness
- [Logging](docs/tools/logging.md) — per-package logging via `$package->log()`
- [Package registry](docs/tools/package-registry.md) — every package built on the toolkit, and whether two claimed one name
- [Path resolver](docs/tools/path-resolver.md) — an explicit level count instead of `__DIR__ . '/../..'`
- [Pint](docs/tools/pint.md) — the shared code-style config
- [Provider builders](docs/tools/provider-builders.md) — force HTTPS, locale, pagination, gates, route groups, events
- [Public names](docs/tools/public-names.md) — why views take `vendor/package::` while Blade tags cannot
- [Publishing](docs/tools/publishing.md) — publish tags, asset groups and orphan pruning
- [Rate limiters](docs/tools/rate-limiters.md) — fluent rate limiters
- [Resilience](docs/tools/resilience.md) — retries, backoff and circuit breaking
- [Runtime services](docs/tools/runtime-services.md) — the services the toolkit resolves at runtime
- [Sbom](docs/tools/sbom.md) — provenance and SBOM generation
- [Scheduling](docs/tools/scheduling.md) — declaring a command's cadence beside the command

### Recipes

- [Scaffolding a package](docs/recipes/scaffolding-a-package.md) — the smallest provider that gives you the conventions
- [Adding a config file](docs/recipes/adding-a-config-file.md) — the flat path, and the id-versus-key distinction
- [Adding a command](docs/recipes/adding-a-command.md) — the namespaced name and the base class it needs
- [Exposing a facade](docs/recipes/exposing-a-facade.md) — singleton, accessor and global alias in one statement
- [Publishing assets](docs/recipes/publishing-assets.md) — declaring the group and re-publishing on upgrade
- [Adding a doctor check](docs/recipes/adding-a-doctor-check.md) — asking a question a human would otherwise ask by hand
- [Scheduling a command](docs/recipes/scheduling-a-command.md) — cadence beside the command, not in the application
- [Testing a package](docs/recipes/testing-a-package.md) — IsolatedTestCase, and the shared-skeleton trap

### Project

- [Changelog](CHANGELOG.md)
- [Contributing](CONTRIBUTING.md)
- [Security](SECURITY.md)

## Contributing & security

Issues and PRs are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md). Report vulnerabilities per
[SECURITY.md](SECURITY.md) (opensource@simtabi.com); participation follows the [Code of Conduct](CODE_OF_CONDUCT.md).

## License

MIT © Simtabi LLC. See [LICENSE](LICENSE).
