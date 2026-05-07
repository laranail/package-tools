# Architecture Documentation

## Overview

`laranail/package-tools` follows a service-oriented architecture with
clear separation of concerns.

> **Historical context.** This package was extracted from
> `laranail/packager` (now [`laranail/package-scaffolder`](https://github.com/laranail/package-scaffolder))
> in May 2026 — Phase 4 of the suite cleanup. The original
> Nov–Dec 2025 planning artifacts (the `PROJECT_PLANS/` directory with
> 28 phase docs + analysis files) are archived under
> `package-scaffolder/.artifacts/legacy-plans-<date>/PROJECT_PLANS/`
> for anyone digging into pre-v9 history. The current canonical plan
> lives at `package-scaffolder/.plans/CLEANUP-MASTER-PLAN.md` (v9).

## Core Components

### 1. Package Class
Central class that aggregates all package concerns through traits.

### 2. Service Layer (34 Services)

#### Config Services
- **ConfigService**: Primary configuration operations
- **ConfigFileResolver**: Path resolution for config files
- **ConfigMerger**: Advanced configuration merging strategies
- **ConfigValidator**: Configuration validation
- **PatternResolver**: Dynamic pattern resolution with variables

#### Asset Services
- **AssetPublisher**: Orchestrates asset publishing
- **AssetRegistry**: Tracks published assets
- **AssetGroupResolver**: Resolves asset groups
- **AssetValidator**: Validates asset paths

#### Component Services
- **ComponentRegistry**: Manages component registration
- **ComponentNamespaceResolver**: Handles namespace resolution
- **AnonymousComponentLoader**: Loads anonymous components
- **ComponentValidator**: Validates component classes

#### View Services
- **ViewComposerRegistry**: Manages view composers
- **ViewComponentLoader**: Loads view components
- **ViewValidator**: Validates view configurations

#### Event Services
- **EventRegistry**: Manages event listeners
- **MiddlewareRegistry**: Manages middleware

#### Development Services
- **TestPublisher**: Publishes test files
- **SecurityChecker**: Performs security checks
- **GitService**: Handles Git operations
- **DependencyAnalyzer**: Analyzes dependencies

#### Package Services
- **PackageValidator**: Validates package structure
- **ComposerService**: Manages Composer operations
- **PackageAnalyzer**: Analyzes package code
- **DependencyResolver**: Resolves dependencies

#### Utility Services
- **ProgressIndicator**: CLI progress bars
- **PathValidator**: Validates paths
- **ConsoleHelper**: Console output helpers

#### Bug Hunter Services
- **BugHunterService**: Orchestrates bug detection
- **NamespaceAnalyzer**: Checks namespace consistency
- **MethodSignatureAnalyzer**: Validates method signatures
- **CodeQualityAnalyzer**: Performs code quality checks

### 3. Package Concerns (51 Traits)

Traits compose package functionality:
- Configuration concerns (4)
- Component concerns (4)
- Asset concerns (4)
- Event & Middleware concerns (2)
- View concerns (2)
- Development concerns (3)
- Advanced concerns (3)
- Plus 29 base concerns

### 4. Generator Sub-Package

Modular package generation toolkit with:
- BlueprintService
- StubService
- PlaceholderService
- PackageStructureService

## Design Patterns

### Service Pattern
All business logic is encapsulated in dedicated service classes with single responsibilities.

### Dependency Injection
Services are injected through constructors for testability.

### Interface Segregation
Services implement specific interfaces (ServiceInterface, PublisherInterface, etc.).

### Fluent Interface
Package class provides fluent API with method chaining.

### Trait Composition
Package class composes functionality through traits.

### Configuration-Driven
All dynamic values are configurable through `config/packager.php`.

## Configuration System

### Pattern Resolution
Uses `PatternResolver` to dynamically resolve strings:

```php
// Pattern: {project}\\{module}\\{component_ns}
// Variables: project=App, module=Blog, component_ns=View\\Components
// Result: App\\Blog\\View\\Components
```

### Auto-Detection
`ConfigDetector` automatically infers project settings from `composer.json`.

## Cross-Platform Support

### Path Resolution
Uses `PathResolver` for consistent path handling across Windows, Linux, macOS, WSL.

### Laravel Helpers
Extensively uses Laravel facades (`File`, `Str`, `Arr`) instead of native PHP functions.

## Testing Strategy

### Unit Tests
Test individual services and concerns in isolation.

### Integration Tests
Test service interactions and complete workflows.

### Automation Scripts
Python and Shell scripts for comprehensive testing.

## Open Source Standards

- PSR-12 coding standards
- PHPStan level 8 compliance
- Psalm static analysis
- 80%+ code coverage
- Security auditing
- Comprehensive documentation
