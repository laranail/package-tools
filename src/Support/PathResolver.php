<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Support;

use ReflectionClass;
use ReflectionException;
use RuntimeException;

/**
 * PathResolver - Cross-platform path resolution for package root detection
 *
 * **IMPROVEMENT #1: Remove External Dependency**
 *
 * This class eliminates the need for the external `simtabi/ichava` dependency
 * by implementing path resolution logic directly in the packager.
 *
 * **Cross-platform Support:**
 * - Windows: Handles both forward slashes and backslashes
 * - WSL: Properly handles /mnt/ paths and mixed separators
 * - Linux: Native UNIX paths
 * - macOS: Native UNIX paths
 *
 * **How it works:**
 * 1. Uses PHP Reflection to get the service provider's file location
 * 2. Normalizes path separators for current OS
 * 3. Traverses up the directory tree by the specified number of levels
 * 4. Returns the calculated package root path with normalized separators
 *
 * **Example:**
 * ```
 * Service Provider: /vendor/my-package/src/Providers/MyServiceProvider.php
 * levelsUp: 3
 *
 * Calculation:
 * - Start: /vendor/my-package/src/Providers/MyServiceProvider.php
 * - dirname() #1: /vendor/my-package/src/Providers
 * - dirname() #2: /vendor/my-package/src
 * - dirname() #3: /vendor/my-package (package root) ✅
 * ```
 */
class PathResolver
{
    /**
     * Normalize path separators for current platform
     *
     * Converts all path separators to the native directory separator
     * and removes duplicate separators.
     *
     * @param string $path Path to normalize
     * @return string Normalized path
     */
    public static function normalizePath(string $path): string
    {
        if ($path === '') {
            return '';
        }

        // Replace both forward and backslashes with native separator
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        // Remove duplicate separators (handles cases like C:\\path\\\\to\\file on Windows)
        $separator = preg_quote(DIRECTORY_SEPARATOR, '/');
        $normalized = preg_replace('/' . $separator . '+/', DIRECTORY_SEPARATOR, $normalized);

        // Resolve `.` and `..` segments. Stack-walk: `.` is a no-op,
        // `..` pops the previous segment unless we're already at the
        // filesystem root (in which case we drop it) or unless the
        // previous segment is itself a leading `..` (relative path).
        $isAbsolute = isset($normalized[0]) && $normalized[0] === DIRECTORY_SEPARATOR;
        $segments = explode(DIRECTORY_SEPARATOR, (string) $normalized);
        $stack = [];
        foreach ($segments as $i => $seg) {
            if ($i === 0 && $seg === '' && $isAbsolute) {
                $stack[] = ''; // preserve the leading slash

                continue;
            }
            if ($seg === '') {
                continue;
            }
            if ($seg === '.') {
                continue;
            }
            if ($seg === '..') {
                $top = end($stack);
                if (! in_array($top, [false, '', '..'], true)) {
                    array_pop($stack);

                    continue;
                }
                if ($isAbsolute) {
                    continue; // can't go above root
                }
            }
            $stack[] = $seg;
        }
        $normalized = implode(DIRECTORY_SEPARATOR, $stack);
        if ($normalized === '' && $isAbsolute) {
            $normalized = DIRECTORY_SEPARATOR;
        }

        // Trim trailing separator (unless it's root like C:\ or /)
        $normalized = rtrim($normalized, DIRECTORY_SEPARATOR);

        // Re-add trailing separator for root paths on Windows (C:) and Unix (/)
        if (self::isRoot($normalized)) {
            $normalized .= DIRECTORY_SEPARATOR;
        }

        return $normalized;
    }

    /**
     * Validate path security (ENHANCEMENT #2: Security validation)
     *
     * Performs comprehensive security checks to prevent:
     * - Path traversal attacks (..)
     * - Absolute path injection (/, \, C:)
     * - Windows drive letter injection
     * - Null byte injection
     *
     * @param string $path Path to validate (should be relative)
     *
     * @throws RuntimeException If path fails security checks
     */
    public static function validatePathSecurity(string $path): void
    {
        $originalPath = $path;
        $path = trim($path);

        if ($path === '') {
            return; // Empty paths are allowed
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

        // Check for null bytes (security vulnerability)
        if (str_contains($path, "\0")) {
            $errors[] = 'contains null bytes';
        }

        // Check for scheme-based paths (http://, file://, etc.)
        if (preg_match('/^[a-z]+:\/\//i', $path)) {
            $errors[] = 'contains URL scheme';
        }

        if ($errors !== []) {
            $reason = implode(', ', $errors);
            throw new RuntimeException(
                "Security violation in path '{$originalPath}': Path {$reason}. " .
                "Use relative paths within the package like 'config/file.php' or 'resources/views'."
            );
        }
    }

    /**
     * Detect module root from a class file (ENHANCEMENT #3: Module root detection)
     *
     * Walks up the directory tree from the class file to find the 'src' directory,
     * then returns its parent as the module root.
     *
     * @param string $classFile Full path to the class file
     * @return string Module root path
     *
     * @throws RuntimeException If 'src' directory not found
     */
    public static function detectModuleRoot(string $classFile): string
    {
        $directory = dirname($classFile);

        // Split path into parts
        $parts = explode(DIRECTORY_SEPARATOR, static::normalizePath($directory));

        // Find the 'src' directory index
        $srcIndex = array_search('src', $parts, true);

        if ($srcIndex !== false && $srcIndex > 0) {
            // Build path up to (but not including) 'src'. Pure path
            // parsing — don't require the directory to exist on disk
            // (callers may compute module roots from symbolic class
            // files in tests, scaffolding, or analysis tooling).
            $moduleRoot = implode(DIRECTORY_SEPARATOR, array_slice($parts, 0, $srcIndex));

            return static::normalizePath($moduleRoot);
        }

        throw new RuntimeException(
            "Could not detect module root from class file: '{$classFile}'. " .
            "Expected to find 'src' directory in path hierarchy."
        );
    }

    /**
     * Check if path is a filesystem root
     *
     * Handles:
     * - Windows: C:\, D:\, etc.
     * - WSL: /mnt/c/, /mnt/d/, etc.
     * - Unix/Linux/macOS: /
     *
     * @param string $path Path to check
     * @return bool True if path is root
     */
    private static function isRoot(string $path): bool
    {
        // Normalize separators first
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $path = rtrim($path, DIRECTORY_SEPARATOR);

        // Unix/Linux/macOS root
        if ($path === '') {
            return true;
        }

        // Windows drive letter (C:, D:, etc.)
        if (preg_match('/^[a-zA-Z]:$/', $path)) {
            return true;
        }

        // WSL mount point (/mnt/c, /mnt/d, etc.)
        return (bool) preg_match('#^' . preg_quote(DIRECTORY_SEPARATOR, '#') . 'mnt' . preg_quote(DIRECTORY_SEPARATOR, '#') . '[a-zA-Z]$#', $path . DIRECTORY_SEPARATOR);
    }

    /**
     * Get parent directory with cross-platform support
     *
     * @param string $path Current path
     * @return string Parent path
     */
    private static function getParentDirectory(string $path): string
    {
        $normalized = static::normalizePath($path);
        $parent = dirname($normalized);

        // Normalize the parent path as well
        return static::normalizePath($parent);
    }

    /**
     * Calculate package root from service provider location
     *
     * Cross-platform compatible: Works on Windows, WSL, Linux, and macOS.
     *
     * @param string|object $caller The service provider class name or instance
     * @param int $levelsUp Number of directory levels to traverse upward
     * @return string Absolute path to package root (normalized for current platform)
     *
     * @throws RuntimeException If reflection fails or invalid levelsUp value
     *
     * @example
     * ```php
     * // From: src/Providers/MyServiceProvider.php
     * PathResolver::packageRootFromProvider($this, 3);
     * // Returns (Linux/macOS): /full/path/to/package/root
     * // Returns (Windows): C:\full\path\to\package\root
     * // Returns (WSL): /mnt/c/full/path/to/package/root
     * ```
     */
    public static function packageRootFromProvider(string|object $caller, int $levelsUp = 3): string
    {
        // Validate input with enhanced range checking
        if ($levelsUp < 1 || $levelsUp > 10) {
            throw new RuntimeException(
                "Invalid levelsUp value: {$levelsUp}. Must be between 1 and 10. " .
                'Common values: 2 (from Providers/), 3 (from src/Providers/), 4 (from packages/vendor/package/src/Providers/)'
            );
        }

        // Resolve to a file path. Three cases:
        //   - object: reflect on its class.
        //   - string that exists on disk: use directly (covers __FILE__).
        //   - string class-name: reflect on it.
        if (is_object($caller)) {
            try {
                $filePath = (new ReflectionClass($caller))->getFileName();
            } catch (ReflectionException $e) {
                throw new RuntimeException("Failed to reflect object of class '" . $caller::class . "': {$e->getMessage()}", $e->getCode(), $e);
            }
        } elseif (is_file($caller)) {
            $filePath = $caller;
        } else {
            try {
                $filePath = (new ReflectionClass($caller))->getFileName();
            } catch (ReflectionException $e) {
                throw new RuntimeException("Failed to reflect class '{$caller}': {$e->getMessage()}", $e->getCode(), $e);
            }
        }

        if ($filePath === false) {
            $describe = is_object($caller) ? $caller::class : (is_string($caller) ? $caller : '<unknown>');
            throw new RuntimeException(
                "Could not determine file path for caller '{$describe}'. " .
                'This may be an internal PHP class or trait.'
            );
        }

        // Normalize the initial file path for the current platform
        $filePath = static::normalizePath($filePath);

        // Get the directory containing the file
        $currentPath = static::normalizePath(dirname($filePath));

        // Traverse up the directory tree
        for ($i = 0; $i < $levelsUp; $i++) {
            $parentPath = self::getParentDirectory($currentPath);

            // Check if we've reached the filesystem root
            // We need to compare normalized paths to handle edge cases
            if (static::normalizePath($parentPath) === static::normalizePath($currentPath) || self::isRoot($currentPath)) {
                $platform = PHP_OS_FAMILY;
                throw new RuntimeException(
                    "Reached filesystem root while traversing up {$levelsUp} levels from '{$filePath}' (Platform: {$platform}). " .
                    "Current level: {$i}. Current path: '{$currentPath}'. " .
                    'You may need to decrease the levelsUp value. ' .
                    "Hint: Count the directories from your service provider file to where 'config/', 'resources/', and 'database/' are located."
                );
            }

            $currentPath = $parentPath;
        }

        return static::normalizePath($currentPath);
    }

    /**
     * Calculate package root with validation
     *
     * Enhanced version that validates the calculated path contains expected directories.
     * This helps catch configuration errors early.
     *
     * Cross-platform compatible with normalized path handling.
     *
     * @param string|object $caller The service provider class name or instance
     * @param int $levelsUp Number of directory levels to traverse upward
     * @param array $expectedDirs Directories that should exist in package root (e.g., ['src', 'config'])
     * @return string Absolute path to package root (normalized for current platform)
     *
     * @throws RuntimeException If validation fails
     *
     * @example
     * ```php
     * PathResolver::packageRootFromProviderWithValidation(
     *     caller: $this,
     *     levelsUp: 3,
     *     expectedDirs: ['src', 'database', 'config']
     * );
     * // Works on: Windows (C:\path\to\package)
     * //           Linux (/path/to/package)
     * //           macOS (/path/to/package)
     * //           WSL (/mnt/c/path/to/package)
     * ```
     */
    public static function packageRootFromProviderWithValidation(
        string|object $caller,
        int $levelsUp = 3,
        array $expectedDirs = []
    ): string {
        $packageRoot = static::packageRootFromProvider($caller, $levelsUp);

        // Validate expected directories exist
        if ($expectedDirs !== []) {
            $missingDirs = [];

            foreach ($expectedDirs as $dir) {
                // Normalize the directory path
                $dirPath = static::normalizePath($dir);
                $fullPath = static::joinPaths($packageRoot, $dirPath);

                if (! is_dir($fullPath)) {
                    $missingDirs[] = $dir;
                }
            }

            if ($missingDirs !== []) {
                $platform = PHP_OS_FAMILY;
                throw new RuntimeException(
                    "Package root validation failed at '{$packageRoot}' (Platform: {$platform}). " .
                    'Missing expected directories: ' . implode(', ', $missingDirs) . '. ' .
                    'You may need to adjust the levelsUp value. ' .
                    "Current levelsUp: {$levelsUp}"
                );
            }
        }

        return $packageRoot;
    }

    /**
     * Join path segments with proper cross-platform separator handling
     *
     * @param string ...$segments Path segments to join
     * @return string Joined and normalized path
     *
     * @example
     * ```php
     * // Works on all platforms:
     * PathResolver::joinPaths('/base', 'config', 'app.php');
     * // Linux/macOS: /base/config/app.php
     * // Windows: C:\base\config\app.php (if /base is C:\base)
     * ```
     */
    public static function joinPaths(string ...$segments): string
    {
        if ($segments === []) {
            return '';
        }

        // Normalize first segment
        $path = static::normalizePath($segments[0]);
        $counter = count($segments);

        // Join remaining segments
        for ($i = 1; $i < $counter; $i++) {
            $segment = trim($segments[$i], '/\\');
            if ($segment !== '') {
                $path .= DIRECTORY_SEPARATOR . $segment;
            }
        }

        return static::normalizePath($path);
    }

    /**
     * Auto-detect optimal levelsUp value
     *
     * Attempts to automatically determine the correct levelsUp value by looking
     * for common package markers (composer.json, src/, etc.).
     *
     * **Note:** This is experimental and may not work for all package structures.
     * Explicit levelsUp value is recommended for production use.
     *
     * Cross-platform compatible.
     *
     * @param string|object $caller The service provider class name or instance
     * @param array $markers Files/dirs that indicate package root (default: ['composer.json', 'src'])
     * @param int $maxLevels Maximum levels to search (default: 10)
     * @return int Detected levelsUp value
     *
     * @throws RuntimeException If package root cannot be detected
     *
     * @example
     * ```php
     * $levelsUp = PathResolver::autoDetectLevelsUp($this);
     * $packageRoot = PathResolver::packageRootFromProvider($this, $levelsUp);
     * // Works on all platforms: Windows, WSL, Linux, macOS
     * ```
     */
    public static function autoDetectLevelsUp(
        string|object $caller,
        array $markers = ['composer.json', 'src'],
        int $maxLevels = 10
    ): int {
        $className = is_object($caller) ? $caller::class : $caller;

        try {
            $reflector = new ReflectionClass($className);
        } catch (ReflectionException $e) {
            throw new RuntimeException("Failed to reflect class '{$className}': {$e->getMessage()}", $e->getCode(), $e);
        }

        $filePath = $reflector->getFileName();
        if ($filePath === false) {
            throw new RuntimeException("Could not determine file path for class '{$className}'.");
        }

        // Normalize the initial path
        $filePath = static::normalizePath($filePath);
        $currentPath = static::normalizePath(dirname($filePath));

        // Search up to maxLevels
        for ($level = 1; $level <= $maxLevels; $level++) {
            $parentPath = self::getParentDirectory($currentPath);

            // Check if we've reached the root
            if (static::normalizePath($parentPath) === static::normalizePath($currentPath) || self::isRoot($currentPath)) {
                break;
            }

            $currentPath = $parentPath;

            // Check for markers
            foreach ($markers as $marker) {
                $markerPath = static::joinPaths($currentPath, $marker);
                if (file_exists($markerPath)) {
                    return $level;
                }
            }
        }

        $platform = PHP_OS_FAMILY;
        throw new RuntimeException(
            "Could not auto-detect package root for class '{$className}' (Platform: {$platform}). " .
            "Searched up to {$maxLevels} levels from '{$filePath}'. " .
            'Please specify levelsUp manually.'
        );
    }

    /**
     * Get relative path between two absolute paths
     *
     * Utility method to calculate relative path from one absolute path to another.
     * Cross-platform compatible with proper separator normalization.
     *
     * @param string $from Starting path
     * @param string $to Target path
     * @return string Relative path
     *
     * @example
     * ```php
     * // Linux/macOS:
     * PathResolver::getRelativePath(
     *     '/var/www/vendor/package',
     *     '/var/www/vendor/package/src/Providers'
     * );
     * // Returns: 'src/Providers' (with / on Unix)
     *
     * // Windows:
     * PathResolver::getRelativePath(
     *     'C:\www\vendor\package',
     *     'C:\www\vendor\package\src\Providers'
     * );
     * // Returns: 'src\Providers' (with \ on Windows)
     * ```
     */
    public static function getRelativePath(string $from, string $to): string
    {
        // Normalize both paths
        $from = static::normalizePath($from);
        $to = static::normalizePath($to);

        // Split paths into parts
        $fromParts = explode(DIRECTORY_SEPARATOR, trim($from, DIRECTORY_SEPARATOR));
        $toParts = explode(DIRECTORY_SEPARATOR, trim($to, DIRECTORY_SEPARATOR));

        // Find common base
        $commonLength = 0;
        $minLength = min(count($fromParts), count($toParts));

        for ($i = 0; $i < $minLength; $i++) {
            // Case-insensitive comparison for Windows
            $fromPart = PHP_OS_FAMILY === 'Windows' ? strtolower($fromParts[$i]) : $fromParts[$i];
            $toPart = PHP_OS_FAMILY === 'Windows' ? strtolower($toParts[$i]) : $toParts[$i];

            if ($fromPart === $toPart) {
                $commonLength++;
            } else {
                break;
            }
        }

        // Build relative path
        $relativeParts = [];
        $counter = count($fromParts);

        // Add '../' for each remaining part in $from
        for ($i = $commonLength; $i < $counter; $i++) {
            $relativeParts[] = '..';
        }
        $counter = count($toParts);

        // Add remaining parts from $to
        for ($i = $commonLength; $i < $counter; $i++) {
            $relativeParts[] = $toParts[$i];
        }

        $result = implode(DIRECTORY_SEPARATOR, $relativeParts);

        return $result ?: '.';
    }

    /**
     * Check if a path is absolute
     *
     * Works across all platforms:
     * - Windows: C:\path, D:\path, \\network\path
     * - WSL: /mnt/c/path
     * - Unix/Linux/macOS: /path
     *
     * @param string $path Path to check
     * @return bool True if path is absolute
     *
     * @example
     * ```php
     * PathResolver::isAbsolutePath('/var/www');        // true (Unix)
     * PathResolver::isAbsolutePath('C:\www');          // true (Windows)
     * PathResolver::isAbsolutePath('/mnt/c/www');      // true (WSL)
     * PathResolver::isAbsolutePath('relative/path');   // false
     * PathResolver::isAbsolutePath('../relative');     // false
     * ```
     */
    public static function isAbsolutePath(string $path): bool
    {
        if ($path === '' || $path === '0') {
            return false;
        }

        // Normalize separators for checking
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        // Unix/Linux/macOS absolute path starts with /
        if ($normalized[0] === DIRECTORY_SEPARATOR) {
            return true;
        }

        // Windows absolute path with drive letter (C:\, D:\, etc.)
        if (strlen($normalized) >= 2 && preg_match('/^[a-zA-Z]:/', $normalized)) {
            return true;
        }

        // Windows UNC path (\\server\share)
        return strlen($normalized) >= 2 && $normalized[0] === '\\' && $normalized[1] === '\\';
    }

    /**
     * Ensure path is absolute, convert relative to absolute if needed
     *
     * @param string $path Path to make absolute
     * @param string|null $basePath Base path for relative paths (defaults to getcwd())
     * @return string Absolute path
     *
     * @example
     * ```php
     * PathResolver::toAbsolutePath('config/app.php', '/var/www/package');
     * // Returns: /var/www/package/config/app.php (Unix)
     * // Returns: C:\www\package\config\app.php (Windows)
     * ```
     */
    public static function toAbsolutePath(string $path, ?string $basePath = null): string
    {
        if (static::isAbsolutePath($path)) {
            return static::normalizePath($path);
        }

        $basePath ??= getcwd();
        if ($basePath === false) {
            throw new RuntimeException('Could not determine current working directory.');
        }

        return static::joinPaths($basePath, $path);
    }

    /**
     * Convert an absolute path to one relative to a given base.
     *
     * Inverse of toAbsolutePath(). Wrapper around getRelativePath() with
     * the more intuitive (path, base) argument order — first argument is
     * the path you have, second is what you want it relative to.
     *
     * @param string $absolutePath Absolute path to convert.
     * @param string $basePath Base path the result is relative to.
     * @return string Relative path. Returns '.' when paths are identical.
     */
    public static function toRelativePath(string $absolutePath, string $basePath): string
    {
        return static::getRelativePath($basePath, $absolutePath);
    }
}
