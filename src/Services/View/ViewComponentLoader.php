<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Services\View;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Simtabi\Laranail\PackageTools\Contracts\LoaderInterface;

/**
 * ViewComponentLoader - View component loading
 *
 * Loads and registers view components
 */
class ViewComponentLoader implements LoaderInterface
{
    protected array $loaded = [];

    public function __construct(protected Application $app) {}

    /**
     * Load view components from a path
     *
     * @param string $path Path to components
     * @param string $namespace Component namespace
     */
    public function loadFromPath(string $path, string $namespace): void
    {
        if (! $this->canLoad($path)) {
            return;
        }

        // Register the namespace
        $prefix = str_replace('/', '-', $namespace);
        $this->loaded[$prefix] = $path;
    }

    /**
     * Load component namespace
     *
     * @param string $namespace Component namespace
     * @param array $components Component classes
     */
    public function loadNamespace(string $namespace, array $components): void
    {
        $prefix = str_replace('/', '-', $namespace);

        Blade::componentNamespace($namespace, $prefix);

        foreach ($components as $name => $class) {
            Blade::component($name, $class);
        }

        $this->loaded[$prefix] = $components;
    }

    /**
     * Load anonymous components
     *
     * @param string $path Components directory path
     * @param string $prefix Component prefix
     */
    public function loadAnonymous(string $path, string $prefix): void
    {
        if (! $this->canLoad($path)) {
            return;
        }

        $normalizedPrefix = str_replace('/', '-', $prefix);
        $this->app['blade.compiler']->anonymousComponentPath($path, $normalizedPrefix);

        $this->loaded[$normalizedPrefix] = $path;
    }

    /**
     * {@inheritDoc}
     */
    public function load(string $path): void
    {
        $this->loadFromPath($path, '');
    }

    /**
     * {@inheritDoc}
     */
    public function canLoad(string $path): bool
    {
        return is_dir($path) && is_readable($path);
    }

    /**
     * {@inheritDoc}
     */
    public function getLoaded(): array
    {
        return $this->loaded;
    }
}
