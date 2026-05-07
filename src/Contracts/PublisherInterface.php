<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Contracts;

/**
 * Publisher Interface
 *
 * Contract for all publishing services (assets, configs, views, etc.)
 * Ensures consistent publishing behavior across different resource types.
 */
interface PublisherInterface
{
    /**
     * Publish a resource from source to target
     *
     * @param string $source Source path
     * @param string $target Target path
     * @param string $tag Publish tag for vendor:publish command
     */
    public function publish(string $source, string $target, string $tag): void;

    /**
     * Check if a resource can be published
     *
     * @param string $source Source path to check
     */
    public function canPublish(string $source): bool;

    /**
     * Get list of published resources
     *
     * @return array<string, string> Array of source => target paths
     */
    public function getPublished(): array;
}
