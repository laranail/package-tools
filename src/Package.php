<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Simtabi\Laranail\PackageTools\Concerns\Package\ConfiguresAssets;
use Simtabi\Laranail\PackageTools\Concerns\Package\ConfiguresCommands;
use Simtabi\Laranail\PackageTools\Concerns\Package\ConfiguresComponents;
// Domain aggregators (ADR-004) — Package only `use`s these. Each aggregator
// composes its leaf traits (Has*) from src/Package/Concerns/Package/.
use Simtabi\Laranail\PackageTools\Concerns\Package\ConfiguresComposer;
use Simtabi\Laranail\PackageTools\Concerns\Package\ConfiguresConfig;
use Simtabi\Laranail\PackageTools\Concerns\Package\ConfiguresDatabase;
use Simtabi\Laranail\PackageTools\Concerns\Package\ConfiguresHelpers;
use Simtabi\Laranail\PackageTools\Concerns\Package\ConfiguresLifecycle;
use Simtabi\Laranail\PackageTools\Concerns\Package\ConfiguresMiddleware;
use Simtabi\Laranail\PackageTools\Concerns\Package\ConfiguresRoutes;
use Simtabi\Laranail\PackageTools\Concerns\Package\ConfiguresServiceProviders;
use Simtabi\Laranail\PackageTools\Concerns\Package\ConfiguresTranslations;
use Simtabi\Laranail\PackageTools\Concerns\Package\ConfiguresViews;
use Simtabi\Laranail\PackageTools\Exceptions\InvalidPackage;
use Simtabi\Laranail\PackageTools\Exceptions\InvalidPath;
use Simtabi\Laranail\PackageTools\Support\PathResolver;

class Package
{
    // 13 domain aggregators wire 44 leaf traits (ADR-004). The remaining
    // 6 leaves (HasCachedNamespaces, HasModuleAssets, HasAssetGroups,
    // HasEventSystem, HasViewComposerRegistry, ManagesComposer) collide
    // by method or property name with already-wired siblings and stay
    // unwired pending consolidation — see ADR-0011.
    use ConfiguresAssets;
    use ConfiguresCommands;
    use ConfiguresComponents;
    use ConfiguresComposer;
    use ConfiguresConfig;
    use ConfiguresDatabase;
    use ConfiguresHelpers;
    use ConfiguresLifecycle;
    use ConfiguresMiddleware;
    use ConfiguresRoutes;
    use ConfiguresServiceProviders;
    use ConfiguresTranslations;
    use ConfiguresViews;

    /** @var string Config directory path relative to package root */
    public const string CONFIG_DIR = 'config';

    /** @var string Views directory path relative to package root */
    public const string VIEWS_DIR = 'resources/views';

    /** @var string Translations directory path relative to package root */
    public const string LANG_DIR = 'resources/lang';

    /** @var string Helpers directory path relative to package root */
    public const string HELPERS_DIR = 'helpers';

    /** @var string Migrations directory path relative to package root */
    public const string MIGRATIONS_DIR = 'database/migrations';

    /** @var string Seeders directory path relative to package root */
    public const string SEEDERS_DIR = 'database/seeders';

    /** @var string Factories directory path relative to package root */
    public const string FACTORIES_DIR = 'database/factories';

    /** @var string Routes directory path relative to package root */
    public const string ROUTES_DIR = 'routes';

    /** @var string Public assets directory path relative to package root */
    public const string PUBLIC_DIR = 'public';

    /** @var string Assets directory path relative to package root */
    public const string ASSETS_DIR = 'resources/assets';

    public string $name = '';

    public string $basePath = '';

    /** @var string|null Config vendor name (extracted from 'vendor/package' format) */
    public ?string $configVendor = null;

    /** @var callable|null Custom name transformation callback */
    protected $nameTransformer;

    /** @var string|null Base publish tag ID (e.g., 'laranail', 'vendor') */
    protected ?string $publishTagId = null;

    /** @var array<string> Cache of built publish tags */
    protected array $builtPublishTags = [];

    /** @var array<string> Allowed tag separators */
    protected array $allowedSeparators = ['::', ':', '-'];

    /** @var array<string, array> Asset paths to publish [tag => [source => destination]] */
    protected array $assetPaths = [];

    /** @var array<string, array> Publish paths [tag => [source => destination]] */
    protected array $publishPaths = [];

    /** @var array<string, bool> Tags that should be cleaned before publishing [tag => true] */
    protected array $publishPathsToClean = [];

    /** @var array<string, string> Component namespaces [namespace => prefix] */
    protected array $componentNamespaces = [];

    /**
     * Set the package name with auto-extracted vendor namespacing
     *
     * **Format: 'vendor/package' (auto-namespaced):**
     * ```php
     * $package->setName('ichava/tabler-icons');
     * // Config: config('ichava.tabler-icons')
     * // Package name: 'tabler-icons'
     * // Vendor: 'ichava'
     * ```
     *
     * **Format: 'package' (no namespacing):**
     * ```php
     * $package->setName('my-package');
     * // Config: config('my-package')
     * // Package name: 'my-package'
     * // Vendor: null
     * ```
     *
     * **With custom transformer:**
     * ```php
     * $package->setName('ichava/laravel-tabler-icons', fn($n) => Str::after($n, 'laravel-'));
     * // Package name: 'tabler-icons' (laravel- stripped)
     * ```
     *
     * **Validation:**
     * - Name cannot be empty or whitespace-only
     * - Name must contain valid characters (alphanumeric, dashes, slashes, underscores)
     *
     * @param string $name Package name in 'vendor/package' or 'package' format
     * @param callable|null $transformer Optional callback to transform package name
     *
     * @throws InvalidPackage
     */
    public function name(string $name, ?callable $transformer = null): static
    {
        // Spatie-compatible fluent alias for setName(). Both work; this
        // one is the documented public surface — see examples + docs.
        return $this->setName($name, $transformer);
    }

    public function setName(string $name, ?callable $transformer = null): static
    {
        // Validate input name is not empty
        $trimmedName = Str::trim($name);
        if (empty($trimmedName)) {
            throw InvalidPackage::nameIsEmpty();
        }

        // Validate character set BEFORE vendor-format check so invalid
        // names get the more-helpful "Invalid package name" exception
        // rather than the vendor-format complaint.
        if (! preg_match('/^[a-zA-Z0-9\/\-_]+$/', $trimmedName)) {
            throw InvalidPackage::nameIsInvalid($trimmedName);
        }

        // Store transformer for later use
        $this->nameTransformer = $transformer;

        // Extract vendor if name contains '/' using Str helper
        if (Str::contains($trimmedName, '/')) {
            $parts = Str::of($trimmedName)->explode('/', 2);
            $vendor = $parts[0];
            $packageName = $parts[1] ?? '';

            // Validate vendor is not empty
            if (empty(Str::trim($vendor))) {
                throw InvalidPackage::nameIsInvalid($name);
            }

            // Validate package name is not empty
            if (empty(Str::trim($packageName))) {
                throw InvalidPackage::nameIsInvalid($name);
            }

            // Apply transformer if provided
            $finalName = $transformer ? $transformer($packageName) : $packageName;

            // Validate final name after transformation
            $finalName = Str::trim($finalName);
            if (empty($finalName)) {
                throw InvalidPackage::nameIsEmpty();
            }

            // Validate name format (alphanumeric, dashes, underscores, slashes allowed)
            if (! preg_match('/^[a-zA-Z0-9\/\-_]+$/', $finalName)) {
                throw InvalidPackage::nameIsInvalid($finalName);
            }

            $this->name = $finalName;
            $this->configVendor = $vendor;
        } else {
            // BREAKING CHANGE: Vendor/package format is now REQUIRED
            // Legacy single package name format is no longer supported
            throw InvalidPackage::vendorRequired($trimmedName);
        }

        return $this;
    }

    /**
     * Get the short package name
     *
     * **Enhanced for vendor/package support:**
     * Since our name() method already extracts the package name from
     * 'vendor/package' format, we just return the name directly.
     *
     * **Original Spatie behavior:**
     * Stripped 'laravel-' prefix (e.g., 'laravel-permission' → 'permission')
     *
     * **Custom transformation:**
     * If a transformer was provided to name(), it's already applied to $this->name
     */
    public function shortName(): string
    {
        // Name is already transformed (if transformer was provided to name())
        // e.g., 'ichava/tabler-icons' → name='tabler-icons' → shortName='tabler-icons'
        // e.g., 'ichava/laravel-icons' + transformer → name='icons' → shortName='icons'
        return $this->name;
    }

    public function basePath(?string $directory = null): string
    {
        if ($directory === null) {
            return $this->basePath;
        }

        // Use Str helper for path joining
        return Str::finish($this->basePath, DIRECTORY_SEPARATOR) . Str::ltrim($directory, DIRECTORY_SEPARATOR);
    }

    /**
     * Set the package base path intelligently from either a path string or file reference
     *
     * This unified method automatically detects the input type and handles it appropriately:
     * - **String path** → Sets path directly
     * - **Object/Class reference** → Auto-detects from file location
     *
     * **Why this is better:**
     * - ✅ One method instead of two - no decision fatigue
     * - ✅ Intelligent auto-detection - just pass what you have
     * - ✅ Same cross-platform support as before
     * - ✅ Backward compatible - old methods still work
     *
     * **Usage Examples:**
     *
     * ```php
     * // Option 1: Auto-detect from service provider (RECOMMENDED)
     * $package->setPathFrom($this);                    // Default: 3 levels up
     * $package->setPathFrom($this, levelsUp: 3);      // Explicit
     *
     * // Option 2: Manual path string
     * $package->setPathFrom(__DIR__ . '/../');
     * $package->setPathFrom(base_path('packages/my-package'));
     * $package->setPathFrom('/absolute/path/to/package');
     *
     * // Option 3: Class name string
     * $package->setPathFrom(MyServiceProvider::class, levelsUp: 3);
     * ```
     *
     * **Cross-platform Support:**
     * Works seamlessly on Windows, WSL, Linux, and macOS with proper path normalization.
     *
     * **Validation:**
     * - Path strings cannot be empty or whitespace-only
     * - File references must be valid classes or objects
     *
     * @param string|object $source Either:
     *                              - A string path (absolute or relative)
     *                              - An object instance (typically `$this` from service provider)
     *                              - A class name string (e.g., `MyServiceProvider::class`)
     * @param int|null $levelsUp Number of directory levels to go up (only used for file-based detection)
     *                           For a file at src/Providers/MyServiceProvider.php:
     *                           - 3 (default) → goes to package root (up from src/Providers/)
     *                           - 2 → goes to src/ directory
     *                           - 4 → goes up one level above package root
     *                           Ignored when $source is a path string.
     *
     * @throws InvalidPath|RuntimeException
     *
     * @example
     * // In your service provider (most common use case)
     * public function configurePackage(Package $package): void
     * {
     *     $package
     *         ->setPathFrom($this)              // Auto-detect from this file
     *         ->setName('vendor/package')
     *         ->hasConfigFile();
     * }
     * @example
     * // Manual path (when auto-detection doesn't work)
     * $package->setPathFrom(__DIR__ . '/../../');
     * @example
     * // Custom levels up
     * $package->setPathFrom($this, levelsUp: 4);  // Go up 4 levels instead of default 3
     */
    public function setPathFrom(string|object $source, ?int $levelsUp = null): static
    {
        // Determine if $source is a path string or a file reference
        $isPathString = $this->isPathString($source);

        if ($isPathString) {
            // Handle as path string
            $trimmedPath = Str::trim((string) $source);
            if (empty($trimmedPath)) {
                throw InvalidPath::pathIsEmpty();
            }

            $this->basePath = $trimmedPath;
        } else {
            // Handle as file reference
            if (! class_exists(PathResolver::class)) {
                throw new RuntimeException(
                    'PathResolver class not found. ' .
                    'Please ensure the Packager package is properly installed and autoloaded.'
                );
            }

            // Use default levelsUp if not provided
            $levelsUp ??= 3;

            $this->basePath = PathResolver::packageRootFromProvider($source, $levelsUp);
        }

        return $this;
    }

    /**
     * Determine if the source is a path string or a file reference
     *
     * Intelligently detects whether the input is:
     * - A file path (string with path separators, absolute paths, etc.)
     * - A class reference (object or class name string)
     *
     * @return bool True if it's a path string, false if it's a file reference
     */
    private function isPathString(string|object $source): bool
    {
        // Objects are always file references (service provider instances)
        if (is_object($source)) {
            return false;
        }

        $string = Str::trim($source);

        // Empty string - treat as path (will throw validation error)
        if (empty($string)) {
            return true;
        }

        // Absolute paths (Unix/Linux/macOS)
        if (Str::startsWith($string, '/')) {
            return true;
        }

        // Absolute paths (Windows: C:\, D:\, etc.)
        if (preg_match('/^[A-Z]:[\\\\\/]/i', $string)) {
            return true;
        }

        // Path patterns (relative paths)
        if (Str::contains($string, '..') || Str::contains($string, './') || Str::contains($string, '.\\')) {
            return true;
        }

        // Path-like constants or functions
        if (preg_match('/^(__DIR__|__FILE__|base_path|public_path|storage_path|app_path|resource_path)/', $string)) {
            return true;
        }

        // Contains forward slashes (likely a path, not a class)
        if (Str::contains($string, '/')) {
            return true;
        }

        // Contains backslashes - could be Windows path or class name
        if (Str::contains($string, '\\')) {
            // If it has both forward and backslashes, it's definitely a path
            if (Str::contains($string, '/')) {
                return true;
            }

            // If it's a simple class name like "MyClass" (no namespace), it's a class
            // But if it has a namespace (contains \), check if it looks like a path
            // Windows paths with backslashes usually have drive letters or start with \\
            if (preg_match('/^[A-Z]:|^\\\\/', $string)) {
                return true; // Windows path
            }

            // Otherwise, assume it's a class name with namespace
            return false;
        }

        // Contains dots (likely a relative path like "./config" or "../src")
        // Simple string without path indicators - assume it's a class name
        // (though this is unlikely in practice, as class names usually have namespaces)
        return Str::contains($string, '.');
    }

    // ============================================================================
    // Publish Tag System (PHASE 2: Enhanced publish tags)
    // ============================================================================

    /**
     * Set the base publish tag ID
     *
     * This ID is used as a prefix for all publish tags generated by buildPublishTag().
     *
     * @param string $id Base tag ID (e.g., 'laranail', 'vendor', 'package')
     *
     * @example
     * ```php
     * $package->setPublishTagId('laranail');
     * $tag = $package->buildPublishTag('config'); // Returns: 'laranail::config'
     * ```
     */
    public function setPublishTagId(string $id): static
    {
        $this->publishTagId = Str::lower(Str::trim($id));

        return $this;
    }

    /**
     * Get the current publish tag ID
     */
    public function getPublishTagId(): ?string
    {
        return $this->publishTagId;
    }

    /**
     * Build a publish tag with validation
     *
     * Generates a publish tag by combining the base tag ID with a name,
     * using the specified separator. All components are validated and normalized.
     *
     * **Supported separators:** '::', ':', '-'
     *
     * **Tag naming rules:**
     * - Only alphanumeric characters, dashes (-), and colons (:)
     * - No spaces or special characters
     * - Automatically lowercased
     *
     * @param string $name Tag name (e.g., 'config', 'blog-assets')
     * @param string $separator Separator between base and name (default: '::')
     * @return string Built and validated publish tag
     *
     * @throws RuntimeException If validation fails
     *
     * @example Basic usage
     * ```php
     * $tag = $package->buildPublishTag('config');
     * // Returns: 'laranail::config'
     * ```
     * @example Nested tags
     * ```php
     * $tag = $package->buildPublishTag('package::imani-tp', '::');
     * // Returns: 'laranail::package::imani-tp'
     * ```
     * @example Different separators
     * ```php
     * $tag = $package->buildPublishTag('config', '-');
     * // Returns: 'laranail-config'
     * ```
     */
    public function buildPublishTag(string $name, string $separator = '::'): string
    {
        // Validate separator
        $this->validatePublishTagSeparator($separator);

        // Clean and validate name
        $name = $this->normalizePublishTagName($name);
        $this->validatePublishTagName($name);

        // Get base tag
        $baseTag = $this->getBasePublishTag();

        // Build full tag
        $fullTag = Str::lower($baseTag . $separator . $name);

        // Cache it
        $this->builtPublishTags[] = $fullTag;

        return $fullTag;
    }

    /**
     * Validate publish tag separator
     *
     * Ensures the separator is one of the allowed values.
     *
     * @param string $separator Separator to validate
     *
     * @throws RuntimeException If separator is invalid
     */
    protected function validatePublishTagSeparator(string $separator): void
    {
        $separator = Str::trim($separator);

        if (empty($separator)) {
            throw new RuntimeException('Publish tag separator cannot be empty');
        }

        if (! in_array($separator, $this->allowedSeparators, true)) {
            throw new RuntimeException(
                "Invalid publish tag separator '{$separator}'. " .
                'Allowed separators are: ' . implode(', ', $this->allowedSeparators)
            );
        }
    }

    /**
     * Validate publish tag name
     *
     * Ensures the name contains only allowed characters.
     *
     * @param string $name Tag name to validate
     *
     * @throws RuntimeException If name is invalid
     */
    protected function validatePublishTagName(string $name): void
    {
        if ($name === '' || $name === '0') {
            throw new RuntimeException('Publish tag name cannot be empty after cleaning');
        }

        // Name can contain alphanumeric, dashes, colons (for nested tags)
        if (! preg_match('/^[a-zA-Z0-9\-:]+$/', $name)) {
            throw new RuntimeException(
                "Invalid publish tag name '{$name}'. " .
                'Only alphanumeric characters, dashes (-), and colons (:) are allowed'
            );
        }
    }

    /**
     * Normalize publish tag name
     *
     * Cleans the name by:
     * - Trimming whitespace
     * - Removing special chars from start/end
     * - Preserving internal structure
     *
     * @param string $name Name to normalize
     * @return string Normalized name
     */
    protected function normalizePublishTagName(string $name): string
    {
        // Trim spaces
        $name = Str::trim($name);

        // Remove non-alphanumeric from start/end but preserve internal structure
        $name = preg_replace('/^[^a-zA-Z0-9]+|[^a-zA-Z0-9]+$/', '', $name);

        return $name;
    }

    /**
     * Get the base publish tag
     *
     * Returns the base tag from:
     * 1. Explicitly set publishTagId
     * 2. Config value (laranail.package.publishing_tag_name)
     * 3. Throws exception if neither is set
     *
     * @return string Base publish tag
     *
     * @throws RuntimeException If no base tag is configured
     */
    protected function getBasePublishTag(): string
    {
        // Check explicit property
        if (! in_array($this->publishTagId, [null, '', '0'], true)) {
            return $this->publishTagId;
        }

        // Check config
        $baseTag = config('laranail.package.publishing_tag_name');

        if (! empty($baseTag)) {
            return $baseTag;
        }

        // Throw exception - must have a base tag
        throw new RuntimeException(
            'You must set a publish tag ID via setPublishTagId() ' .
            'or configure laranail.package.publishing_tag_name in your config'
        );
    }

    /**
     * Get all built publish tags
     *
     * Returns the cache of all tags built during this request.
     *
     * @return array<string>
     */
    public function getBuiltPublishTags(): array
    {
        return $this->builtPublishTags;
    }

    /**
     * Clear the publish tags cache
     */
    public function clearPublishTagsCache(): static
    {
        $this->builtPublishTags = [];

        return $this;
    }

    // ============================================================================
    // Abstract Method Implementations (Required by Concerns)
    // ============================================================================

    /**
     * Get view namespace
     *
     * Required by HasEnhancedViewComposers trait
     */
    protected function getViewNamespace(): string
    {
        return $this->viewNamespace();
    }

    /**
     * Get config namespace
     *
     * Required by HasAdvancedConfig trait
     */
    protected function getConfigNamespace(): string
    {
        return $this->getDottedNamespace();
    }

    /**
     * Get package name in kebab-case
     *
     * Required by HasAssetPublisher trait
     */
    protected function getPackageKebabName(): string
    {
        return Str::kebab($this->name);
    }

    /**
     * Get package base path with optional subdirectory
     *
     * Required by multiple concerns (HasEventSystem, HasModuleAssets, etc.)
     *
     * @param string $path Optional subdirectory path
     */
    protected function packageBasePath(string $path = ''): string
    {
        return $this->basePath($path);
    }

    /**
     * Publish assets
     *
     * Required by HasAssetPublisher, HasVueAssets, HasAssetGroups traits
     *
     * @param array $paths Array of paths to publish [source => destination]
     * @param string $tag Publish tag
     */
    protected function publishAssets(array $paths, string $tag): void
    {
        // This will be handled by the service provider's ProcessAssets trait
        // Store for later publishing during boot
        if (! isset($this->assetPaths)) {
            $this->assetPaths = [];
        }
        $this->assetPaths[$tag] = $paths;
    }

    /**
     * Publish resources.
     *
     * Internal multi-path publish primitive — registers paths into
     * $this->publishPaths[$tag] for the service provider's boot pass to
     * actually publish. Used by various Has*Assets traits.
     *
     * @param array $paths Array of paths to publish [source => destination]
     * @param string $tag Publish tag
     */
    protected function publishes(array $paths, string $tag): void
    {
        // Store for later publishing during boot
        if (! isset($this->publishPaths)) {
            $this->publishPaths = [];
        }
        $this->publishPaths[$tag] = $paths;
    }

    /**
     * Publish ANY resources with FULL flexibility
     *
     * This is the ultimate "free-ride" method that allows you to publish
     * absolutely anything, anywhere, with any tag format you want.
     * No restrictions, no limitations - complete freedom.
     *
     * @param array<string, string> $paths Array of paths to publish [source => destination]
     *                                     - Source paths can be absolute or relative to package base path
     *                                     - Destination paths can be anywhere in the Laravel application
     *                                     - Supports wildcards and patterns
     * @param string $tag Publish tag (fully customizable, any format)
     *                    - Examples: 'my-custom-tag', 'vendor::package-custom', 'anything-you-want'
     * @param bool $cleanBeforePublish Whether to clean destination before publishing
     * @param callable|null $transformer Optional callback to transform paths: fn(string $source, string $dest) => ['newSource' => 'newDest']
     *
     * @example
     * // Publish custom assets
     * $package->publish([
     *     'resources/custom/icons' => 'public/vendor/blog/icons',
     *     'resources/custom/themes' => 'storage/app/blog/themes',
     * ], 'blog-custom-assets');
     * @example
     * // Publish with cleanup
     * $package->publish([
     *     'resources/old-assets' => 'public/vendor/blog',
     * ], 'blog-assets', cleanBeforePublish: true);
     * @example
     * // Publish with path transformation
     * $package->publish([
     *     'resources/data' => 'storage/app/data',
     * ], 'blog-data', transformer: function($source, $dest) {
     *     return [
     *         $source . '/processed' => $dest . '/processed',
     *     ];
     * });
     * @example
     * // Publish multiple unrelated resources
     * $package->publish([
     *     'config/custom.php' => 'config/custom.php',
     *     'database/seeds' => 'database/seeds/vendor/blog',
     *     'resources/lang/custom' => 'lang/vendor/blog',
     * ], 'blog-misc');
     * @example
     * // Use any tag format you want
     * $package->publish([...], 'my-company::blog::assets');
     * $package->publish([...], 'blog.assets.v2');
     * $package->publish([...], 'anything::you::want');
     */
    public function publish(array $paths, string $tag, bool $cleanBeforePublish = false, ?callable $transformer = null): static
    {
        // Validate inputs
        if ($paths === []) {
            throw new InvalidArgumentException('Publish paths cannot be empty. Provide at least one source => destination mapping.');
        }

        if (empty(Str::trim($tag))) {
            throw new InvalidArgumentException('Publish tag cannot be empty.');
        }

        // Apply transformer if provided
        if ($transformer !== null) {
            $transformedPaths = [];
            foreach ($paths as $source => $destination) {
                $result = $transformer($source, $destination);
                if (is_array($result)) {
                    $transformedPaths = Arr::merge($transformedPaths, $result);
                } else {
                    throw new InvalidArgumentException('Transformer callback must return an array of [source => destination] mappings.');
                }
            }
            $paths = $transformedPaths;
        }

        // Resolve relative source paths to absolute paths
        $resolvedPaths = [];
        foreach ($paths as $source => $destination) {
            // If source is relative, resolve against package base path
            if (! Str::startsWith($source, '/') && ! preg_match('/^[A-Z]:\\\\/', $source)) {
                $source = $this->basePath($source);
            }

            $resolvedPaths[$source] = $destination;
        }

        // Store for later publishing during boot
        if (! isset($this->publishPaths)) {
            $this->publishPaths = [];
        }

        // If cleanBeforePublish is true, mark this tag for cleanup
        if ($cleanBeforePublish) {
            if (! isset($this->publishPathsToClean)) {
                $this->publishPathsToClean = [];
            }
            $this->publishPathsToClean[$tag] = true;
        }

        // Merge with existing paths for the same tag (allows multiple calls)
        if (isset($this->publishPaths[$tag])) {
            $this->publishPaths[$tag] = Arr::merge($this->publishPaths[$tag], $resolvedPaths);
        } else {
            $this->publishPaths[$tag] = $resolvedPaths;
        }

        return $this;
    }

    /**
     * Register component namespace
     *
     * Required by HasComponentNamespaces trait
     *
     * @param string $namespace Component namespace
     * @param string $prefix Component prefix
     */
    protected function registerComponentNamespace(string $namespace, string $prefix): void
    {
        // Store for later registration during boot
        if (! isset($this->componentNamespaces)) {
            $this->componentNamespaces = [];
        }
        $this->componentNamespaces[$namespace] = $prefix;
    }

    /**
     * Get all custom publish paths registered via publish() method
     *
     * @return array<string, array> Array of publish paths [tag => [source => destination]]
     */
    public function getPublishPaths(): array
    {
        return $this->publishPaths ?? [];
    }

    /**
     * Get tags that should be cleaned before publishing
     *
     * @return array<string, bool> Array of tags to clean [tag => true]
     */
    public function getPublishPathsToClean(): array
    {
        return $this->publishPathsToClean ?? [];
    }
}
