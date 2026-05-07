<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Contracts;

/**
 * Registry Interface
 *
 * Contract for registry services that track and manage registered items
 * (components, events, middleware, etc.)
 */
interface RegistryInterface
{
    /**
     * Register an item with a key
     *
     * @param string $key Unique identifier
     * @param mixed $value Value to register
     */
    public function register(string $key, mixed $value): void;

    /**
     * Get all registered items
     *
     * @return array<string, mixed>
     */
    public function getRegistered(): array;

    /**
     * Check if an item is registered
     *
     * @param string $key Item identifier
     */
    public function has(string $key): bool;

    /**
     * Get a registered item by key
     *
     * @param string $key Item identifier
     * @param mixed $default Default value if not found
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Unregister an item
     *
     * @param string $key Item identifier
     */
    public function unregister(string $key): void;
}
