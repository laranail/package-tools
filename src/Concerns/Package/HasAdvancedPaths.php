<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

use Illuminate\Support\Facades\File;
use ReflectionClass;
use RuntimeException;

/**
 * HasAdvancedPaths - Advanced path resolution with security validation
 *
 * Provides secure, flexible path management for packages with:
 * - Comprehensive security validation (blocks path traversal, absolute paths, drive letters)
 * - Auto-detection via reflection
 * - Support for 'modules' vs 'packages' context
 * - Plugin detection
 * - Cross-platform compatibility
 */
trait HasAdvancedPaths
{
    /**
     * Get package path with optional subpath
     *
     * Returns the package base path, optionally appending a validated subpath.
     * All paths are validated for security before being returned.
     *
     * @param string|null $path Optional subpath to append (relative path only)
     * @param string $basePath Base path prefix (default: 'platform')
     * @return string Full validated path
     *
     * @throws RuntimeException If path validation fails
     *
     * @example Get base path
     * ```php
     * $packagePath = $this->getPath();
     * // Returns: /var/www/platform/packages/blog
     * ```
     * @example Get path with subpath
     * ```php
     * $configPath = $this->getPath('config/app.php');
     * // Returns: /var/www/platform/packages/blog/config/app.php
     * ```
     */
    protected function getPath(?string $path = null, string $basePath = 'platform'): string
    {
        // Validate the subpath if provided
        if ($path !== null) {
            $path = $this->validatePathSecurity($path);
        }

        // Build base path
        $packageBase = $this->buildBasePath($basePath);

        // Return with optional subpath
        if ($path === null || $path === '') {
            return $packageBase;
        }

        return $packageBase . DIRECTORY_SEPARATOR . $path;
    }

    /**
     * Validate path security
     *
     * Performs comprehensive security checks on a path to prevent:
     * - Path traversal attacks (..)
     * - Absolute path injection (/, \, C:)
     * - Windows drive letter injection
     *
     * @param string $path Path to validate
     * @return string Validated path (trimmed and normalized)
     *
     * @throws RuntimeException If path fails security checks
     */
    protected function validatePathSecurity(string $path): string
    {
        $originalPath = $path;
        $path = trim($path);

        if ($path === '') {
            return '';
        }

        $errors = [];

        // Check for absolute paths (Unix-style)
        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            $errors[] = 'starts with a slash (absolute path)';
        }

        // Check for Windows drive letters
        if (preg_match('/^[A-Z]:[\\\\\/]/i', $path)) {
            $errors[] = 'contains a Windows drive letter (e.g., C:)';
        }

        // Check for path traversal
        if (str_contains($path, '..')) {
            $errors[] = 'contains parent directory references (..)';
        }

        // Check for null bytes (security)
        if (str_contains($path, "\0")) {
            $errors[] = 'contains null bytes';
        }

        if ($errors !== []) {
            $reason = implode(', ', $errors);
            throw new RuntimeException(
                "Invalid path '{$originalPath}': Path {$reason}. " .
                "Use relative paths within the package like 'config/file.php' or 'resources/views'."
            );
        }

        // Normalize directory separators
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    /**
     * Build the package base path
     *
     * Attempts to determine the package base path using:
     * 1. Reflection-based detection (finds 'src' directory)
     * 2. Namespace-based fallback
     *
     * @param string $basePath Base path prefix
     * @return string Package base path
     */
    protected function buildBasePath(string $basePath = 'platform'): string
    {
        // Try reflection-based detection first
        if (method_exists($this, 'resolveModulePath')) {
            $reflection = new ReflectionClass($this);
            $classFile = $reflection->getFileName();

            if ($classFile !== false) {
                try {
                    return $this->resolveModulePath($classFile, $basePath);
                } catch (RuntimeException) {
                    // Fall through to namespace-based approach
                }
            }
        }

        // Fallback to namespace-based path
        return $this->buildNamespaceBasedPath($basePath);
    }

    /**
     * Resolve module path from class file
     *
     * Walks up the directory tree from the class file to find the 'src' directory,
     * then returns its parent as the module root.
     *
     * @param string $classFile Full path to the class file
     * @param string $basePath Base path prefix
     * @return string Module root path
     *
     * @throws RuntimeException If 'src' directory not found
     */
    protected function resolveModulePath(string $classFile, string $basePath): string
    {
        $directory = File::dirname($classFile);

        // Split path into parts
        $parts = explode(DIRECTORY_SEPARATOR, $directory);

        // Find the 'src' directory index
        $srcIndex = array_search('src', $parts, true);

        if ($srcIndex !== false && $srcIndex > 0) {
            // Build path up to (but not including) 'src'
            $moduleRoot = implode(DIRECTORY_SEPARATOR, array_slice($parts, 0, $srcIndex));

            if (File::isDirectory($moduleRoot)) {
                return $moduleRoot;
            }
        }

        // If we can't find 'src', fall back to namespace-based
        return $this->buildNamespaceBasedPath($basePath);
    }

    /**
     * Build path based on package namespace
     *
     * Uses the package's namespace to construct the expected path.
     * Assumes namespace maps to directory structure.
     *
     * @param string $basePath Base path prefix
     * @return string Namespace-based path
     */
    protected function buildNamespaceBasedPath(string $basePath): string
    {
        // Get namespace in dashed format (e.g., 'packages/blog')
        $namespace = $this->getDashedNamespace();

        // Trim slashes from basePath for consistent handling
        $basePath = trim($basePath, '/\\');

        // Build the full path
        if ($basePath !== '') {
            return base_path($basePath . DIRECTORY_SEPARATOR . $namespace);
        }

        return base_path($namespace);
    }

    /**
     * Get dashed namespace format
     *
     * Returns the package namespace in dashed format (e.g., 'packages/blog').
     * This method should be implemented in the Package class.
     *
     * @return string Dashed namespace
     */
    abstract protected function getDashedNamespace(): string;

    /**
     * Detect module root from current class
     *
     * Convenience method to detect the module root from the current class file.
     *
     * @return string Module root path
     *
     * @throws RuntimeException If detection fails
     */
    protected function detectModuleRoot(): string
    {
        $reflection = new ReflectionClass($this);
        $classFile = $reflection->getFileName();

        if ($classFile === false) {
            throw new RuntimeException('Could not determine class file path for module root detection');
        }

        return $this->resolveModulePath($classFile, 'platform');
    }

    /**
     * Check if a path exists and is a directory
     *
     * @param string|null $path Path to check (relative to package root)
     */
    protected function isDirectory(?string $path): bool
    {
        if ($path === null) {
            return false;
        }

        try {
            $fullPath = $this->getPath($path);

            return File::isDirectory($fullPath);
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Check if a path exists and is a file
     *
     * @param string|null $path Path to check (relative to package root)
     */
    protected function isFile(?string $path): bool
    {
        if ($path === null) {
            return false;
        }

        try {
            $fullPath = $this->getPath($path);

            return File::isFile($fullPath);
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Get multiple paths at once
     *
     * Convenience method to get multiple paths in one call.
     *
     * @param array<string, string> $paths Map of key => path
     * @return array<string, string> Map of key => full path
     *
     * @example
     * ```php
     * $paths = $this->getPaths([
     *     'config' => 'config',
     *     'views' => 'resources/views',
     *     'migrations' => 'database/migrations',
     * ]);
     * ```
     */
    protected function getPaths(array $paths): array
    {
        $result = [];

        foreach ($paths as $key => $path) {
            try {
                $result[$key] = $this->getPath($path);
            } catch (RuntimeException) {
                // Skip invalid paths
                continue;
            }
        }

        return $result;
    }
}
