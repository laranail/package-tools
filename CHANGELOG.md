# Changelog

All notable changes to `laranail/package-tools` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-06-26

Initial stable release — a runtime base library for building Laravel packages: the
fluent `Package` builder + abstract `PackageServiceProvider`, namespaced/nested
config, attribute discovery, a seeder subsystem, and the `package-tools.doctor` /
`.sbom` / `.audit` / `.ide-helper` commands. Requires `laranail/console ^1.0`.
