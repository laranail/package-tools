<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Contracts;

/**
 * Loader Interface
 *
 * Contract for loader services that load resources
 * (components, helpers, translations, etc.)
 */
interface LoaderInterface
{
    /**
     * Load a resource
     *
     * @param string $path Path to resource
     */
    public function load(string $path): void;

    /**
     * Check if a resource can be loaded
     *
     * @param string $path Path to check
     */
    public function canLoad(string $path): bool;

    /**
     * Get list of loaded resources
     *
     * @return array<string>
     */
    public function getLoaded(): array;
}
