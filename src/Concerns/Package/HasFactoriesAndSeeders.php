<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederManager;
use Simtabi\Laranail\Package\Tools\Support\Definitions\AutoSeederDefinition;

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

    /** @var list<AutoSeederDefinition> */
    protected array $packageSeederDefinitions = [];

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
                /**
                 * @param class-string<Model> $modelName
                 * @return class-string<Factory<Model>>
                 */
                $resolver = function (string $modelName) use ($fullPath): string {
                    $factoryBasename = class_basename($modelName);
                    $factoryName = $factoryBasename . 'Factory';
                    $factoryFile = $fullPath . DIRECTORY_SEPARATOR . $factoryName . '.php';

                    if (File::exists($factoryFile)) {
                        require_once $factoryFile;

                        $namespace = $this->guessFactoryNamespace();
                        $factoryClass = $namespace . '\\' . $factoryName;

                        if (class_exists($factoryClass) && is_subclass_of($factoryClass, Factory::class)) {
                            return $factoryClass;
                        }
                    }

                    // Not a package factory — fall back to Laravel's
                    // conventional resolution so the host app keeps working.
                    /** @var class-string<Factory<Model>> $fallback */
                    $fallback = Factory::$namespace . class_basename($modelName) . 'Factory';

                    return $fallback;
                };

                Factory::guessFactoryNamesUsing($resolver);
            }
        }
    }

    /**
     * Register the package's seeders to run with the host app's
     * `php artisan db:seed` (via the SeederManager's resolver hook).
     * Accepts a fluent AutoSeederDefinition for full control — discovery,
     * ignore lists, config gating, priority — or the string + array
     * shorthand for the simple case. Seeders NEVER execute at package
     * boot; registration happens at boot, execution at db:seed time.
     *
     * @param list<class-string<Seeder>> $seeders execution order = array order
     */
    public function hasPackageSeeders(AutoSeederDefinition|string $key, array $seeders = []): static
    {
        $this->packageSeederDefinitions[] = $key instanceof AutoSeederDefinition
            ? $key
            : AutoSeederDefinition::make($key)->seeders($seeders);

        return $this;
    }

    /**
     * Discover Seeder subclasses under `$path` and register them for
     * db:seed-time execution (sugar over the definition's discovery mode).
     */
    public function discoverPackageSeedersIn(string $path, ?string $namespace = null): static
    {
        $key = $namespace ?? 'discovered:' . md5($path);

        return $this->hasPackageSeeders(
            AutoSeederDefinition::make($key)->discoverIn($path)->inNamespace($namespace),
        );
    }

    /**
     * @return list<AutoSeederDefinition>
     */
    public function getPackageSeederDefinitions(): array
    {
        return $this->packageSeederDefinitions;
    }

    /**
     * Called from the deferred-hooks chain: evaluates each definition's
     * config gate and registers the surviving bundles with the shared
     * SeederManager. Registration only — execution belongs to db:seed.
     */
    public function bootPackageAutoSeeders(): void
    {
        if ($this->packageSeederDefinitions === [] || ! function_exists('app')) {
            return;
        }

        $defaultDiscoveryPath = $this->basePath('/' . self::SEEDERS_DIR);

        /** @var SeederManager $manager */
        $manager = app(SeederManager::class);

        foreach ($this->packageSeederDefinitions as $definition) {
            if (! $definition->shouldRegister()) {
                continue;
            }

            $seeders = $definition->resolveSeeders($defaultDiscoveryPath);

            if ($seeders === []) {
                continue;
            }

            $manager->autoSeed(
                $definition->key(),
                $seeders,
                $definition->namespace(),
                [...$definition->optionsValue(), 'priority' => $definition->priorityValue()],
            );
        }
    }

    /**
     * Resolve a relative factory path to an absolute one.
     *
     * @param string $path Relative path
     * @return string Absolute path
     */
    protected function resolveFactoryPath(string $path): string
    {
        return $this->getPath($path);
    }

    /**
     * Guess the factory namespace from the package name.
     */
    protected function guessFactoryNamespace(): string
    {
        $parts = explode('/', $this->name);
        if (count($parts) === 2) {
            $vendor = ucfirst($parts[0]);
            $package = str_replace(['-', '_'], '', ucwords($parts[1], '-_'));

            return "{$vendor}\\{$package}\\Database\\Factories";
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
