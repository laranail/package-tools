<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * HasAdditionalNamespaceFormats - Extended namespace format support
 *
 * **IMPROVEMENT #4: Additional Namespace Formats**
 *
 * Adds underscore and camelCase namespace formats for different use cases:
 * - Underscore: Database tables, cache keys, environment variables
 * - CamelCase: JavaScript variables, class names, method names
 * - Snake Case: File names, function names
 * - Kebab Case: URLs, CSS classes (already covered by getDashedNamespace)
 *
 * **Examples:**
 * ```
 * Package: 'ichava/tabler-icons'
 *
 * - Underscore:     ichava_tabler_icons    (DB tables, env vars)
 * - CamelCase:      ichavaTablerIcons      (JS variables)
 * - Pascal Case:    IchavaTablerIcons      (Class names)
 * - Snake Case:     ichava_tabler_icons    (Functions)
 * - Kebab Case:     ichava-tabler-icons    (URLs, CSS)
 * - Dotted:         ichava.tabler-icons    (Config keys)
 * ```
 */
trait HasAdditionalNamespaceFormats
{
    /**
     * Get underscore namespace: 'vendor_package'
     *
     * Perfect for:
     * - Database table names
     * - Cache keys
     * - Environment variables
     * - Snake case function names
     *
     *
     * @example
     * $package->name('ichava/tabler-icons');
     * $package->getUnderscoreNamespace(); // 'ichava_tabler_icons'
     *
     * // Usage examples:
     * $table = $package->getUnderscoreNamespace() . '_icons'; // 'ichava_tabler_icons_icons'
     * $cacheKey = 'cache:' . $package->getUnderscoreNamespace(); // 'cache:ichava_tabler_icons'
     * $envVar = strtoupper($package->getUnderscoreNamespace()); // 'ICHAVA_TABLER_ICONS'
     */
    public function getUnderscoreNamespace(): string
    {
        if ($this->configVendor === null) {
            return Str::snake(str_replace('-', '_', $this->name));
        }

        return Str::snake(
            str_replace('-', '_', $this->configVendor) . '_' .
            str_replace('-', '_', $this->name)
        );
    }

    /**
     * Get snake case namespace: 'vendor_package'
     *
     * Alias for getUnderscoreNamespace() for Laravel naming convention consistency.
     */
    public function getSnakeCaseNamespace(): string
    {
        return $this->getUnderscoreNamespace();
    }

    /**
     * Get camelCase namespace: 'vendorPackage'
     *
     * Perfect for:
     * - JavaScript variable names
     * - JavaScript object properties
     * - JSON keys
     * - Method names
     *
     *
     * @example
     * $package->name('ichava/tabler-icons');
     * $package->getCamelCaseNamespace(); // 'ichavaTablerIcons'
     *
     * // Usage in JavaScript:
     * const {$package->getCamelCaseNamespace()}Config = { ... };
     * window.{$package->getCamelCaseNamespace()} = new IconManager();
     */
    public function getCamelCaseNamespace(): string
    {
        if ($this->configVendor === null) {
            return Str::camel($this->name);
        }

        return Str::camel($this->configVendor . '-' . $this->name);
    }

    /**
     * Get PascalCase namespace: 'VendorPackage'
     *
     * Perfect for:
     * - PHP class names
     * - JavaScript class/constructor names
     * - Component names
     * - Type names
     *
     *
     * @example
     * $package->name('ichava/tabler-icons');
     * $package->getPascalCaseNamespace(); // 'IchavaTablerIcons'
     *
     * // Usage examples:
     * class {$package->getPascalCaseNamespace()}Manager { ... }
     * interface {$package->getPascalCaseNamespace()}Interface { ... }
     */
    public function getPascalCaseNamespace(): string
    {
        if ($this->configVendor === null) {
            return Str::studly($this->name);
        }

        return Str::studly($this->configVendor . '-' . $this->name);
    }

    /**
     * Get StudlyCase namespace: 'VendorPackage'
     *
     * Alias for getPascalCaseNamespace() for Laravel naming convention consistency.
     */
    public function getStudlyCaseNamespace(): string
    {
        return $this->getPascalCaseNamespace();
    }

    /**
     * Get kebab-case namespace: 'vendor-package'
     *
     * Alias for getDashedNamespace() for naming convention consistency.
     * Perfect for URLs and CSS classes.
     */
    public function getKebabCaseNamespace(): string
    {
        return $this->getDashedNamespace();
    }

    /**
     * Get space-separated namespace: 'vendor package'
     *
     * Perfect for:
     * - Display names
     * - Human-readable titles
     * - Breadcrumbs
     *
     * @param bool $titleCase Whether to convert to Title Case
     *
     * @example
     * $package->name('ichava/tabler-icons');
     * $package->getSpacedNamespace();        // 'ichava tabler icons'
     * $package->getSpacedNamespace(true);    // 'Ichava Tabler Icons'
     */
    public function getSpacedNamespace(bool $titleCase = false): string
    {
        if ($this->configVendor === null) {
            $result = str_replace(['-', '_'], ' ', $this->name);
        } else {
            $result = str_replace(['-', '_'], ' ', $this->configVendor . ' ' . $this->name);
        }

        return $titleCase ? Str::title($result) : $result;
    }

    /**
     * Get title case namespace: 'Vendor Package'
     *
     * Shortcut for getSpacedNamespace(titleCase: true).
     *
     *
     * @example
     * $package->name('ichava/tabler-icons');
     * $package->getTitleCaseNamespace(); // 'Ichava Tabler Icons'
     */
    public function getTitleCaseNamespace(): string
    {
        return $this->getSpacedNamespace(true);
    }

    /**
     * Get SCREAMING_SNAKE_CASE namespace: 'VENDOR_PACKAGE'
     *
     * Perfect for:
     * - Constants
     * - Environment variables
     * - Configuration keys
     *
     *
     * @example
     * $package->name('ichava/tabler-icons');
     * $package->getScreamingSnakeCaseNamespace(); // 'ICHAVA_TABLER_ICONS'
     *
     * // Usage:
     * define($package->getScreamingSnakeCaseNamespace() . '_VERSION', '1.0.0');
     * $_ENV[$package->getScreamingSnakeCaseNamespace() . '_API_KEY'] = 'secret';
     */
    public function getScreamingSnakeCaseNamespace(): string
    {
        return Str::upper($this->getUnderscoreNamespace());
    }

    /**
     * Get URL-safe namespace: 'vendor-package'
     *
     * Alias for getKebabCaseNamespace() with URL-safety guarantee.
     *
     *
     * @example
     * $package->name('ichava/tabler-icons');
     * $url = '/packages/' . $package->getUrlSafeNamespace(); // '/packages/ichava-tabler-icons'
     */
    public function getUrlSafeNamespace(): string
    {
        return $this->getKebabCaseNamespace();
    }

    /**
     * Get all namespace formats
     *
     * Returns an array of all available namespace formats.
     * Useful for debugging or documentation.
     *
     * @return array<string, string>
     *
     * @example
     * $package->name('ichava/tabler-icons');
     * $formats = $package->getAllNamespaceFormats();
     * // [
     * //     'dotted' => 'ichava.tabler-icons',
     * //     'dashed' => 'ichava-tabler-icons',
     * //     'doubleColon' => 'ichava::tabler-icons',
     * //     'slash' => 'ichava/tabler-icons',
     * //     'underscore' => 'ichava_tabler_icons',
     * //     'camelCase' => 'ichavaTablerIcons',
     * //     'pascalCase' => 'IchavaTablerIcons',
     * //     'kebabCase' => 'ichava-tabler-icons',
     * //     'snakeCase' => 'ichava_tabler_icons',
     * //     'screamingSnake' => 'ICHAVA_TABLER_ICONS',
     * //     'spaced' => 'ichava tabler icons',
     * //     'titleCase' => 'Ichava Tabler Icons',
     * // ]
     */
    public function getAllNamespaceFormats(): array
    {
        return [
            'dotted' => $this->getDottedNamespace(),
            'dashed' => $this->getDashedNamespace(),
            'doubleColon' => $this->getDoubleColonNamespace(),
            'slash' => $this->getSlashNamespace(),
            'underscore' => $this->getUnderscoreNamespace(),
            'camelCase' => $this->getCamelCaseNamespace(),
            'pascalCase' => $this->getPascalCaseNamespace(),
            'kebabCase' => $this->getKebabCaseNamespace(),
            'snakeCase' => $this->getSnakeCaseNamespace(),
            'screamingSnake' => $this->getScreamingSnakeCaseNamespace(),
            'spaced' => $this->getSpacedNamespace(),
            'titleCase' => $this->getTitleCaseNamespace(),
        ];
    }

    /**
     * Get namespace in custom format using conversion map
     *
     * Allows runtime format specification for dynamic use cases.
     *
     * @param string $format Format name (e.g., 'camelCase', 'snake_case', etc.)
     *
     * @throws InvalidArgumentException If format is not supported
     *
     * @example
     * $package->name('ichava/tabler-icons');
     * $package->getNamespaceFormat('camelCase');      // 'ichavaTablerIcons'
     * $package->getNamespaceFormat('snake_case');     // 'ichava_tabler_icons'
     * $package->getNamespaceFormat('SCREAMING_SNAKE'); // 'ICHAVA_TABLER_ICONS'
     */
    public function getNamespaceFormat(string $format): string
    {
        $formats = [
            'dotted' => fn () => $this->getDottedNamespace(),
            'dashed' => fn () => $this->getDashedNamespace(),
            'kebab' => fn () => $this->getKebabCaseNamespace(),
            'kebab-case' => fn () => $this->getKebabCaseNamespace(),
            'doubleColon' => fn () => $this->getDoubleColonNamespace(),
            'double-colon' => fn () => $this->getDoubleColonNamespace(),
            'slash' => fn () => $this->getSlashNamespace(),
            'underscore' => fn () => $this->getUnderscoreNamespace(),
            'snake' => fn () => $this->getSnakeCaseNamespace(),
            'snake_case' => fn () => $this->getSnakeCaseNamespace(),
            'camel' => fn () => $this->getCamelCaseNamespace(),
            'camelCase' => fn () => $this->getCamelCaseNamespace(),
            'pascal' => fn () => $this->getPascalCaseNamespace(),
            'PascalCase' => fn () => $this->getPascalCaseNamespace(),
            'studly' => fn () => $this->getStudlyCaseNamespace(),
            'StudlyCase' => fn () => $this->getStudlyCaseNamespace(),
            'screaming' => fn () => $this->getScreamingSnakeCaseNamespace(),
            'SCREAMING_SNAKE' => fn () => $this->getScreamingSnakeCaseNamespace(),
            'spaced' => fn () => $this->getSpacedNamespace(),
            'title' => fn () => $this->getTitleCaseNamespace(),
            'titleCase' => fn () => $this->getTitleCaseNamespace(),
        ];

        $normalizedFormat = Str::lower(trim($format));

        if (! isset($formats[$normalizedFormat])) {
            throw new InvalidArgumentException(
                "Unsupported namespace format: '{$format}'. " .
                'Supported formats: ' . implode(', ', array_keys($formats))
            );
        }

        return $formats[$normalizedFormat]();
    }
}
