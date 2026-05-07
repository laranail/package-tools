<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Support;

use Illuminate\Support\Facades\File;

/**
 * ConfigDetector - Auto-detects project configuration
 *
 * Automatically detects project namespace, vendor name, and structure
 * from composer.json and directory layout.
 */
class ConfigDetector
{
    protected ?array $composerData = null;

    public function __construct(
        protected string $basePath = ''
    ) {
        $this->basePath = $basePath ?: base_path();
    }

    /**
     * Detect project namespace from composer.json PSR-4 autoload
     *
     * @return string Detected namespace or 'App' as fallback
     */
    public function detectProjectNamespace(): string
    {
        $composer = $this->getComposerData();

        if (! $composer) {
            return 'App';
        }

        // Check PSR-4 autoload section
        $psr4 = $composer['autoload']['psr-4'] ?? [];

        // Look for namespace that points to app/ or src/
        $targetPaths = ['app/', 'src/'];

        foreach ($psr4 as $namespace => $path) {
            if (in_array($path, $targetPaths, true)) {
                return rtrim((string) $namespace, '\\');
            }
        }

        // If not found, return the first PSR-4 namespace
        if (! empty($psr4)) {
            $firstNamespace = array_key_first($psr4);

            return rtrim((string) $firstNamespace, '\\');
        }

        return 'App';
    }

    /**
     * Detect vendor name from composer.json
     *
     * @return string Vendor name or 'vendor' as fallback
     */
    public function detectVendorName(): string
    {
        $composer = $this->getComposerData();

        if (! $composer || ! isset($composer['name'])) {
            return 'vendor';
        }

        $parts = explode('/', $composer['name']);

        return $parts[0] ?? 'vendor';
    }

    /**
     * Detect package name from composer.json
     *
     * @return string Package name or 'package' as fallback
     */
    public function detectPackageName(): string
    {
        $composer = $this->getComposerData();

        if (! $composer || ! isset($composer['name'])) {
            return 'package';
        }

        $parts = explode('/', $composer['name']);

        return $parts[1] ?? 'package';
    }

    /**
     * Detect project structure type
     *
     * @return string 'standard', 'modular', or 'monorepo'
     */
    public function detectStructureType(): string
    {
        $basePath = $this->basePath;

        // Check for monorepo structure
        if (File::isDirectory($basePath . '/packages') &&
            File::isDirectory($basePath . '/platform')) {
            return 'monorepo';
        }

        // Check for modular structure
        if (File::isDirectory($basePath . '/modules') ||
            File::isDirectory($basePath . '/app/Modules')) {
            return 'modular';
        }

        return 'standard';
    }

    /**
     * Detect paths configuration based on project structure
     *
     * @return array{modules: string, packages: string, base: string}
     */
    public function detectPaths(): array
    {
        $structure = $this->detectStructureType();
        $basePath = $this->basePath;

        return match ($structure) {
            'monorepo' => [
                'modules' => 'modules',
                'packages' => 'platform/packages',
                'base' => $basePath,
            ],
            'modular' => [
                'modules' => File::isDirectory($basePath . '/modules') ? 'modules' : 'app/Modules',
                'packages' => 'packages',
                'base' => $basePath,
            ],
            default => [
                'modules' => 'modules',
                'packages' => 'packages',
                'base' => $basePath,
            ],
        };
    }

    /**
     * Detect tag prefix from vendor name
     *
     * @return string Tag prefix
     */
    public function detectTagPrefix(): string
    {
        $vendor = $this->detectVendorName();

        return strtolower($vendor);
    }

    /**
     * Get full auto-detected configuration
     *
     * @return array<string, mixed>
     */
    public function detectAll(): array
    {
        return [
            'namespace' => $this->detectProjectNamespace(),
            'vendor' => $this->detectVendorName(),
            'package' => $this->detectPackageName(),
            'tag_prefix' => $this->detectTagPrefix(),
            'structure' => $this->detectStructureType(),
            'paths' => $this->detectPaths(),
        ];
    }

    /**
     * Get composer.json data
     */
    protected function getComposerData(): ?array
    {
        if ($this->composerData !== null) {
            return $this->composerData;
        }

        $composerPath = $this->basePath . '/composer.json';

        if (! File::exists($composerPath)) {
            return null;
        }

        $contents = File::get($composerPath);
        $this->composerData = json_decode($contents, true);

        return $this->composerData;
    }

    /**
     * Check if auto-detection is available
     */
    public function canAutoDetect(): bool
    {
        return File::exists($this->basePath . '/composer.json');
    }
}
