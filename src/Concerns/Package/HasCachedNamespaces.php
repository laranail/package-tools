<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Illuminate\Support\Str;

/**
 * Memoizes computed namespace strings so each format is calculated once
 * and reused.
 */
trait HasCachedNamespaces
{
    /** @var array<string, string> Cache for computed namespace strings */
    private array $namespaceCache = [];

    /**
     * Get dotted namespace with caching: 'vendor.package'
     */
    public function getDottedNamespace(): string
    {
        return $this->getCachedNamespace('dotted', function () {
            if ($this->configVendor === null) {
                return $this->name;
            }

            return Str::of($this->configVendor)
                ->append('.', $this->name)
                ->toString();
        });
    }

    /**
     * Get dashed namespace with caching: 'vendor-package'
     */
    public function getDashedNamespace(): string
    {
        return $this->getCachedNamespace('dashed', function () {
            if ($this->configVendor === null) {
                return $this->name;
            }

            return Str::of($this->configVendor)
                ->append('-', $this->name)
                ->toString();
        });
    }

    /**
     * Get double-colon namespace with caching: 'vendor::package'
     */
    public function getDoubleColonNamespace(): string
    {
        return $this->getCachedNamespace('doubleColon', function () {
            if ($this->configVendor === null) {
                return $this->name;
            }

            return Str::of($this->configVendor)
                ->append('::', $this->name)
                ->toString();
        });
    }

    /**
     * Get slash namespace with caching: 'vendor/package'
     */
    public function getSlashNamespace(): string
    {
        return $this->getCachedNamespace('slash', function () {
            if ($this->configVendor === null) {
                return $this->name;
            }

            return Str::of($this->configVendor)
                ->append('/', $this->name)
                ->toString();
        });
    }

    /**
     * Get or compute cached namespace
     *
     * @param string $key Cache key
     * @param callable $generator Function to generate value if not cached
     */
    private function getCachedNamespace(string $key, callable $generator): string
    {
        if (! isset($this->namespaceCache[$key])) {
            $this->namespaceCache[$key] = $generator();
        }

        return $this->namespaceCache[$key];
    }

    /**
     * Clear the namespace cache.
     *
     * Use after changing the package name or vendor.
     */
    public function clearNamespaceCache(): static
    {
        $this->namespaceCache = [];

        return $this;
    }

    /**
     * Get all cached namespaces.
     *
     * @return array<string, string>
     */
    public function getCachedNamespaces(): array
    {
        return $this->namespaceCache;
    }

    /**
     * Pre-compute all namespace formats.
     *
     * Call from the service provider's register() method to avoid lazy
     * computation later.
     */
    public function warmNamespaceCache(): static
    {
        $this->getDottedNamespace();
        $this->getDashedNamespace();
        $this->getDoubleColonNamespace();
        $this->getSlashNamespace();

        return $this;
    }
}
