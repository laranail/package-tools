<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Asset;

use Illuminate\Support\Facades\File;
use Simtabi\Laranail\Package\Tools\Contracts\RegistryInterface;

/**
 * Tracks published assets and manages cleanup.
 */
class AssetRegistry implements RegistryInterface
{
    /** @var array<string, array<int, mixed>> */
    protected array $registered = [];

    /** @var array<string, array<int, mixed>> */
    protected array $cleanupTargets = [];

    /**
     * {@inheritDoc}
     */
    public function register(string $key, mixed $value, bool $shouldCleanup = false): void
    {
        if (! isset($this->registered[$key])) {
            $this->registered[$key] = [];
        }

        $this->registered[$key][] = $value;

        if ($shouldCleanup) {
            $this->cleanupTargets[$key][] = $value;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getRegistered(): array
    {
        return $this->registered;
    }

    /**
     * Get registered assets for a specific tag
     *
     * @param string $tag Publish tag
     * @return array<string>
     */
    public function getByTag(string $tag): array
    {
        return $this->registered[$tag] ?? [];
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $key): bool
    {
        return isset($this->registered[$key]);
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->registered[$key] ?? $default;
    }

    /**
     * {@inheritDoc}
     */
    public function unregister(string $key): void
    {
        unset($this->registered[$key], $this->cleanupTargets[$key]);
    }

    /**
     * Cleanup published assets for a tag
     *
     * @param string $tag Publish tag
     */
    public function cleanup(string $tag): void
    {
        if (! isset($this->cleanupTargets[$tag])) {
            return;
        }

        foreach ($this->cleanupTargets[$tag] as $target) {
            if (File::exists($target)) {
                if (File::isDirectory($target)) {
                    File::deleteDirectory($target);
                } else {
                    File::delete($target);
                }
            }
        }
    }

    /**
     * Check if a tag should be cleaned up
     *
     * @param string $tag Publish tag
     */
    public function shouldCleanup(string $tag): bool
    {
        return isset($this->cleanupTargets[$tag]) && (isset($this->cleanupTargets[$tag]) && $this->cleanupTargets[$tag] !== []);
    }

    /**
     * Get all cleanup targets
     *
     * @return array<string, array<string>>
     */
    public function getCleanupTargets(): array
    {
        return $this->cleanupTargets;
    }
}
