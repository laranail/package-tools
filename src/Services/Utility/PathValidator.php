<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Services\Utility;

use Simtabi\Laranail\PackageTools\Contracts\ValidatorInterface;

/**
 * PathValidator - Path validation
 *
 * Validates paths for security and cross-platform compatibility
 */
class PathValidator implements ValidatorInterface
{
    protected array $errors = [];

    /**
     * Validate input and return array of errors (empty array if valid)
     *
     * @param mixed $input Value to validate (can be string path or array with 'path' key)
     * @return array<string> Array of validation errors
     */
    public function validate(mixed $input): array
    {
        $this->errors = [];

        if (is_string($input)) {
            // Validate single path string
            return $this->validatePathString($input);
        }

        if (is_array($input)) {
            // Validate path data array
            if (! isset($input['path'])) {
                $this->errors[] = 'Path is required';

                return $this->errors;
            }

            return $this->validatePathString($input['path']);
        }

        $this->errors[] = 'Invalid input type. Expected string (path) or array (path data)';

        return $this->errors;
    }

    /**
     * Check if input is valid
     *
     * @param mixed $input Value to validate
     */
    public function isValid(mixed $input): bool
    {
        return $this->validate($input) === [];
    }

    /**
     * Validate a single path string
     *
     * @param string $path Path to validate
     * @return array<string> Array of validation errors
     */
    protected function validatePathString(string $path): array
    {
        $this->errors = [];

        // Check for empty path
        if ($path === '' || $path === '0') {
            $this->errors[] = 'Path cannot be empty';

            return $this->errors;
        }

        // Check for directory traversal attempts
        if ($this->hasDirectoryTraversal($path)) {
            $this->errors[] = 'Path contains directory traversal sequences';
        }

        // Check for null bytes
        if (str_contains($path, "\0")) {
            $this->errors[] = 'Path contains null bytes';
        }

        return $this->errors;
    }

    /**
     * Validate a single path
     *
     * @param string $path Path to validate
     * @return bool True if valid, false otherwise
     */
    public function validatePath(string $path): bool
    {
        $errors = $this->validate($path);

        return $errors === [];
    }

    /**
     * Validate cross-platform path
     *
     * @param string $path Path to validate
     * @return array<string, mixed> Validation results with warnings
     */
    public function validateCrossPlatform(string $path): array
    {
        $issues = [];
        $warnings = [];

        // Check for Windows-specific path separators on Unix
        if (DIRECTORY_SEPARATOR === '/' && str_contains($path, '\\')) {
            $warnings[] = 'Path contains backslashes on Unix system';
        }

        // Check for invalid characters on Windows
        if (preg_match('/[<>:"|?*]/', $path)) {
            $issues[] = 'Path contains characters invalid on Windows';
        }

        // Check for trailing spaces (problematic on Windows)
        if (rtrim($path) !== $path) {
            $warnings[] = 'Path has trailing spaces';
        }

        // Check for reserved names on Windows
        $basename = basename($path);
        $reserved = ['CON', 'PRN', 'AUX', 'NUL', 'COM1', 'COM2', 'COM3', 'COM4', 'LPT1', 'LPT2', 'LPT3'];
        if (in_array(strtoupper($basename), $reserved, true)) {
            $issues[] = 'Path uses reserved name on Windows';
        }

        return [
            'valid' => $issues === [],
            'issues' => $issues,
            'warnings' => $warnings,
        ];
    }

    /**
     * Check if path contains directory traversal sequences
     *
     * @param string $path Path to check
     */
    protected function hasDirectoryTraversal(string $path): bool
    {
        // Normalize path
        $normalized = str_replace('\\', '/', $path);

        // Check for ../ sequences
        if (str_contains($normalized, '../') || str_contains($normalized, '/..')) {
            return true;
        }

        // Check for absolute paths trying to escape
        return str_starts_with($normalized, '/..') || str_starts_with($normalized, '..');
    }

    /**
     * Sanitize path for safe usage
     *
     * @param string $path Path to sanitize
     * @return string Sanitized path
     */
    public function sanitize(string $path): string
    {
        // Remove null bytes
        $path = str_replace("\0", '', $path);

        // Normalize directory separators
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        // Remove duplicate separators
        $path = preg_replace('#' . preg_quote(DIRECTORY_SEPARATOR, '#') . '+#', DIRECTORY_SEPARATOR, $path);

        return $path;
    }
}
