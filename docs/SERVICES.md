# Services API Reference

## Configuration Services

### ConfigService
Primary configuration management service.

```php
$config = app(ConfigService::class);
$value = $config->get('packager.project.namespace', 'App');
$config->set('packager.custom_key', 'value');
```

**Methods:**
- `get(string $key, mixed $default = null): mixed`
- `set(string $key, mixed $value): void`
- `has(string $key): bool`
- `all(): array`

### PatternResolver
Resolves dynamic patterns with variables.

```php
$resolver = app(PatternResolver::class);
$result = $resolver->resolve('{prefix}-{module}-{type}', [
    'prefix' => 'app',
    'module' => 'blog',
    'type' => 'config',
]); // Returns: app-blog-config
```

**Methods:**
- `resolve(string $pattern, array $variables): string`
- `getAvailableVariables(): array`
- `validatePattern(string $pattern): bool`

### ConfigDetector
Auto-detects project configuration.

```php
$detector = app(ConfigDetector::class);
$namespace = $detector->detectProjectNamespace(); // Returns: App
$vendor = $detector->detectVendorName(); // Returns: vendor
```

**Methods:**
- `detectProjectNamespace(): string`
- `detectVendorName(): string`

## Asset Services

### AssetPublisher
Orchestrates asset publishing.

```php
$publisher = app(AssetPublisher::class);
$publisher->publish($source, $target, 'app-blog-assets');
$publisher->publishAssetGroups(['css', 'js'], $basePath, 'blog');
$publisher->publishModuleAssets(null, $basePath, 'blog');
```

**Methods:**
- `publish(string $source, string $target, string $tag): void`
- `publishAssetGroups(array $groups, string $basePath, string $module): void`
- `publishModuleAssets(?array $types, string $basePath, string $module): void`

### AssetRegistry
Tracks published assets.

```php
$registry = app(AssetRegistry::class);
$registry->register('blog', 'css', '/path/to/css');
$assets = $registry->getAll();
$moduleAssets = $registry->getByModule('blog');
```

## Component Services

### ComponentNamespaceResolver
Resolves component namespaces dynamically.

```php
$resolver = app(ComponentNamespaceResolver::class);
$namespace = $resolver->buildNamespace('blog'); // App\Blog\View\Components
$prefix = $resolver->getPrefix('App\Blog\View\Components'); // blog
```

**Methods:**
- `resolve(string $namespace, array $data = []): string`
- `normalize(string $prefix): string`
- `buildNamespace(string $module): string`
- `getPrefix(string $namespace): string`

## Bug Hunter Services

### BugHunterService
Master orchestrator for bug detection.

```php
$bugHunter = app(BugHunterService::class);
$issues = $bugHunter->scan('/path/to/code');
$report = $bugHunter->generateReport();
```

### NamespaceAnalyzer
Checks namespace consistency.

```php
$analyzer = app(NamespaceAnalyzer::class);
$issues = $analyzer->analyze('/path/to/src');
```

### MethodSignatureAnalyzer
Validates method signatures.

```php
$analyzer = app(MethodSignatureAnalyzer::class);
$issues = $analyzer->analyze('/path/to/src');
```

## Development Services

### SecurityChecker
Performs security checks.

```php
$checker = app(SecurityChecker::class);
$vulnerabilities = $checker->check();
```

### GitService
Handles Git operations.

```php
$git = app(GitService::class);
$branch = $git->getCurrentBranch();
$status = $git->getStatus();
```

## Using Services in Your Package

### Via Dependency Injection

```php
use Simtabi\Laranail\Packager\Services\Config\ConfigService;

class MyService
{
    public function __construct(
        protected ConfigService $config
    ) {}
    
    public function myMethod()
    {
        $namespace = $this->config->get('packager.project.namespace');
        // ...
    }
}
```

### Via Service Container

```php
$config = app(ConfigService::class);
$value = $config->get('key');
```

### Via Facade (if defined)

```php
use Packager;

$value = Packager::config('key');
```
