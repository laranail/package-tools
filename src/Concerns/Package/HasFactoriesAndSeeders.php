<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Simtabi\Laranail\PackageTools\Services\Database\SeederExecutor;
use Simtabi\Laranail\PackageTools\Services\Database\SeederPathDiscoverer;
use Simtabi\Laranail\PackageTools\Services\Database\SeederRegistry;

/**
 * Registers factory and seeder paths and boots them with Laravel.
 */
trait HasFactoriesAndSeeders
{
    /** @var array<string> Registered factory paths */
    protected array $factoryPaths = [];

    /** @var array<string> Registered seeder paths */
    protected array $seederPaths = [];

    /** @var array<string> Registered seeders */
    protected array $seeders = [];

    /**
     * Per-package seeder registry. Lazily-instantiated; consumers reach
     * for it via `hasPackageSeeders()` / `discoverPackageSeedersIn()`.
     */
    protected ?SeederRegistry $packageSeederRegistry = null;

    /**
     * Load factories from a directory
     *
     * @param string $path Path to factories directory (relative to package root)
     *
     * @example
     * ```php
     * $package->loadFactoriesFrom('database/factories');
     * ```
     */
    public function loadFactoriesFrom(string $path): static
    {
        $this->factoryPaths[] = $path;

        return $this;
    }

    /**
     * Load seeders from a directory
     *
     * @param string $path Path to seeders directory (relative to package root)
     */
    public function loadSeedersFrom(string $path): static
    {
        $this->seederPaths[] = $path;

        return $this;
    }

    /**
     * Register a seeder class
     *
     * @param string $seederClass Seeder class name
     */
    public function registerSeeder(string $seederClass): static
    {
        $this->seeders[] = $seederClass;

        return $this;
    }

    /**
     * Register the factory paths with Laravel. Call from the service
     * provider's boot() method.
     */
    public function bootPackageFactories(): void
    {
        foreach ($this->factoryPaths as $path) {
            $fullPath = $this->resolveFactoryPath($path);

            if (File::isDirectory($fullPath)) {
                Factory::guessFactoryNamesUsing(function (string $modelName) use ($fullPath): ?string {
                    $factoryBasename = class_basename($modelName);
                    $factoryName = $factoryBasename . 'Factory';
                    $factoryFile = $fullPath . DIRECTORY_SEPARATOR . $factoryName . '.php';

                    if (File::exists($factoryFile)) {
                        require_once $factoryFile;

                        $namespace = $this->guessFactoryNamespace();
                        $factoryClass = $namespace . '\\' . $factoryName;

                        if (class_exists($factoryClass)) {
                            return $factoryClass;
                        }
                    }

                    return null;
                });
            }
        }
    }

    /**
     * Register a bundle of package-contributed seeders to run when the
     * host app's `DatabaseSeeder` resolves.
     *
     * @param string $key Opaque label, typically the package namespace.
     * @param list<class-string<Seeder>> $seeders
     * @param array<string, mixed> $options
     *                                      - `disable_foreign_key_checks` (bool, default true)
     *                                      - `fire_events` (bool, default false) — emit `SeedingStarted`/`SeedingFinished`
     *                                      - `parameters` (array<string, mixed>) — passed to seeders that accept ctor args
     */
    public function hasPackageSeeders(
        string $key,
        array $seeders,
        ?string $namespace = null,
        array $options = [],
    ): static {
        $this->packageSeederRegistry()->register($key, $seeders, $namespace, $options);

        return $this;
    }

    /**
     * Discover Seeder subclasses under `$path` and register them.
     *
     * @param array<string, mixed> $options
     */
    public function discoverPackageSeedersIn(
        string $path,
        ?string $namespace = null,
        array $options = [],
    ): static {
        $discovered = (new SeederPathDiscoverer)->discover($path);
        if ($discovered === []) {
            return $this;
        }

        $key = $namespace ?? 'discovered:' . md5($path);

        return $this->hasPackageSeeders($key, $discovered, $namespace, $options);
    }

    public function packageSeederRegistry(): SeederRegistry
    {
        return $this->packageSeederRegistry ??= new SeederRegistry;
    }

    /**
     * Boot package seeders. Called from `PackageServiceProvider` via the
     * deferred-hooks chain. Runs every registered configuration through
     * a `SeederExecutor`.
     */
    public function bootPackageSeeders(): void
    {
        $registry = $this->packageSeederRegistry;
        if ($registry === null || $registry->isEmpty()) {
            return;
        }

        if (! function_exists('app')) {
            return;
        }

        (new SeederExecutor(app()))->run($registry);
    }

    /**
     * Resolve a relative factory path to an absolute one.
     *
     * @param string $path Relative path
     * @return string Absolute path
     */
    protected function resolveFactoryPath(string $path): string
    {
        if (method_exists($this, 'getPath')) {
            return $this->getPath($path);
        }

        return $this->basePath . DIRECTORY_SEPARATOR . $path;
    }

    /**
     * Guess the factory namespace from the package name.
     */
    protected function guessFactoryNamespace(): string
    {
        if (property_exists($this, 'name')) {
            $parts = explode('/', $this->name);
            if (count($parts) === 2) {
                $vendor = ucfirst($parts[0]);
                $package = str_replace(['-', '_'], '', ucwords($parts[1], '-_'));

                return "{$vendor}\\{$package}\\Database\\Factories";
            }
        }

        return 'Database\\Factories';
    }

    /**
     * Get all factory paths
     *
     * @return array<string>
     */
    public function getFactoryPaths(): array
    {
        return $this->factoryPaths;
    }

    /**
     * Get all seeder paths
     *
     * @return array<string>
     */
    public function getSeederPaths(): array
    {
        return $this->seederPaths;
    }

    /**
     * Get all registered seeders
     *
     * @return array<string>
     */
    public function getRegisteredSeeders(): array
    {
        return $this->seeders;
    }
}
