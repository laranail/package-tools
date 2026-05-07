# Configuration Guide

## Overview

Laranail Packager is 100% configurable with zero hardcoded values.

## Configuration File

Publish the configuration file:

```bash
php artisan vendor:publish --tag=packager-config
```

This creates `config/packager.php`:

```php
return [
    'project' => [
        'namespace' => env('PACKAGER_NAMESPACE', 'App'),
        'tag_prefix' => env('PACKAGER_TAG_PREFIX', 'app'),
        'vendor' => env('PACKAGER_VENDOR', 'vendor'),
    ],
    
    'paths' => [
        'modules' => env('PACKAGER_MODULES_PATH', 'modules'),
        'packages' => env('PACKAGER_PACKAGES_PATH', 'packages'),
        'base' => env('PACKAGER_BASE_PATH', base_path()),
    ],
    
    'namespaces' => [
        'view_components' => 'View\\Components',
        'livewire' => 'Http\\Livewire',
        'controllers' => 'Http\\Controllers',
        'models' => 'Models',
    ],
    
    'patterns' => [
        'component_namespace' => '{project}\\{module}\\{component_ns}',
        'publish_tag' => '{prefix}-{module}-{type}',
    ],
];
```

## Configuration Options

### Project Settings

#### `project.namespace`
Your application's root namespace (auto-detected from `composer.json`).

**Default:** `App`  
**Environment:** `PACKAGER_NAMESPACE`

**Examples:**
```php
'namespace' => 'MyApp',           // Standard Laravel
'namespace' => 'Acme\\Platform',  // Custom namespace
```

#### `project.tag_prefix`
Prefix for publish tags (e.g., `app-blog-config`).

**Default:** `app`  
**Environment:** `PACKAGER_TAG_PREFIX`

#### `project.vendor`
Your vendor name for packages.

**Default:** `vendor`  
**Environment:** `PACKAGER_VENDOR`

### Path Configuration

#### `paths.modules`
Base directory for modules (modular applications).

**Default:** `modules`  
**Environment:** `PACKAGER_MODULES_PATH`

#### `paths.packages`
Base directory for local packages.

**Default:** `packages`  
**Environment:** `PACKAGER_PACKAGES_PATH`

#### `paths.base`
Application base path.

**Default:** `base_path()`  
**Environment:** `PACKAGER_BASE_PATH`

### Namespace Configuration

Define standard namespaces for different component types:

```php
'namespaces' => [
    'view_components' => 'View\\Components',
    'livewire' => 'Http\\Livewire',
    'controllers' => 'Http\\Controllers',
    'models' => 'Models',
],
```

### Pattern Configuration

Define dynamic patterns with placeholders:

#### `patterns.component_namespace`
Pattern for component namespaces.

**Default:** `{project}\\{module}\\{component_ns}`

**Available Variables:**
- `{project}`: Root namespace
- `{module}`: Module name (StudlyCase)
- `{component_ns}`: Component namespace

#### `patterns.publish_tag`
Pattern for publish tags.

**Default:** `{prefix}-{module}-{type}`

**Available Variables:**
- `{prefix}`: Tag prefix
- `{module}`: Module name (kebab-case)
- `{type}`: Asset type

## Project-Specific Examples

### Standard Laravel App

```php
'project' => [
    'namespace' => 'App',
    'tag_prefix' => 'myapp',
],
'paths' => [
    'packages' => 'packages',
],
```

### Modular Application

```php
'project' => [
    'namespace' => 'MyCompany\\Platform',
    'tag_prefix' => 'platform',
],
'paths' => [
    'modules' => 'app/Modules',
    'packages' => 'packages',
],
```

### Multi-Tenant SaaS

```php
'project' => [
    'namespace' => 'Acme\\SaaS',
    'tag_prefix' => 'acme',
],
'patterns' => [
    'component_namespace' => '{project}\\Tenants\\{module}\\{component_ns}',
],
```

## Environment Variables

All settings can be overridden via `.env`:

```env
PACKAGER_NAMESPACE=MyApp
PACKAGER_TAG_PREFIX=myapp
PACKAGER_VENDOR=mycompany
PACKAGER_MODULES_PATH=app/Modules
PACKAGER_PACKAGES_PATH=local-packages
```

## Auto-Detection

If no configuration is provided, Packager auto-detects settings from `composer.json`:

- Namespace: From `autoload.psr-4`
- Vendor: From `name` field

This ensures zero-configuration for most Laravel projects.
