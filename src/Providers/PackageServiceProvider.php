<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Providers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Override;
use ReflectionClass;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider\LoadsHelpers;
use Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider\ProcessAssets;
use Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider\ProcessBladeComponents;
use Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider\ProcessBladeDirectives;
use Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider\ProcessCommands;
use Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider\ProcessConfigs;
use Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider\ProcessInertia;
use Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider\ProcessLivewireComponents;
use Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider\ProcessMigrations;
use Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider\ProcessRoutes;
use Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider\ProcessServiceProviders;
use Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider\ProcessTranslations;
use Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider\ProcessViewComposers;
use Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider\ProcessViews;
use Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider\ProcessViewSharedData;
use Simtabi\Laranail\Package\Tools\Exceptions\InvalidPackage;
use Simtabi\Laranail\Package\Tools\Exceptions\InvalidPath;
use Simtabi\Laranail\Package\Tools\Package;

/**
 * Base service provider for Laravel packages. Manages the package
 * lifecycle and exposes hooks for infrastructure setup, service
 * registration, and boot-time operations.
 *
 * Hooks, by phase:
 *   - registeringPackage(): log channels, Horizon supervisors, morph maps
 *     and other infrastructure that must exist before any service uses it.
 *   - configurePackage(): package name, paths, and features.
 *   - packageRegistered(): singletons, bindings, and aliases (package
 *     config is available by now).
 *   - bootingPackage(): middleware and Blade namespaces, before routes and
 *     views register.
 *   - packageBooted(): event listeners, schedulers, and finalization, once
 *     everything else is ready.
 *
 * Laravel runs all providers' register phase before any boot phase, so
 * place work in the hook whose preconditions are met.
 *
 * @see https://laravel.com/docs/providers
 */
abstract class PackageServiceProvider extends ServiceProvider
{
    use LoadsHelpers;
    use ProcessAssets;
    use ProcessBladeComponents;
    use ProcessBladeDirectives;
    use ProcessCommands;
    use ProcessConfigs;
    use ProcessInertia;
    use ProcessLivewireComponents;
    use ProcessMigrations;
    use ProcessRoutes;
    use ProcessServiceProviders;
    use ProcessTranslations;
    use ProcessViewComposers;
    use ProcessViews;
    use ProcessViewSharedData;

    public Package $package;

    abstract public function configurePackage(Package $package): void;

    /**
     * Register application services. Override registerPackage() for custom
     * registration logic.
     *
     * Laravel's base ServiceProvider::register() is empty, so there is no
     * parent::register() to call.
     *
     * @throws InvalidPackage|InvalidPath
     */
    #[Override]
    public function register(): void
    {
        $this->registerPackage();
    }

    /**
     * Orchestrate package registration: fire the registeringPackage() hook,
     * build and configure the Package, validate it, load helpers, register
     * configs, then fire packageRegistered().
     *
     * Override to customize the flow; call parent::registerPackage() to keep
     * the default behavior.
     *
     * @throws InvalidPackage|InvalidPath
     */
    public function registerPackage(): void
    {
        $this->registeringPackage();

        $this->package = $this->newPackage();
        $this->package->setPathFrom($this->getPackageBaseDir());

        $this->configurePackage($this->package);

        if ($this->package->name === '' || $this->package->name === '0') {
            throw InvalidPackage::nameIsRequired();
        }

        if ($this->package->basePath === '' || $this->package->basePath === '0') {
            throw InvalidPath::pathIsRequired();
        }

        $this->loadPackageHelpers();

        $this->registerPackageConfigs();

        $this->packageRegistered();
    }

    /**
     * Hook called before package registration begins. Override for setup
     * that must run first: environment-specific config, early bindings that
     * don't depend on the package, and shared resources.
     */
    public function registeringPackage(): void
    {
        // Override in child class to add custom pre-registration logic.
    }

    /**
     * Create a new Package instance. Override to use a custom Package
     * subclass or inject dependencies.
     */
    public function newPackage(): Package
    {
        return new Package;
    }

    /**
     * Hook called after package registration completes. Override for
     * bindings that depend on the package, event listeners, package-specific
     * services, and post-registration validation.
     */
    public function packageRegistered(): void
    {
        // Override in child class to add custom post-registration logic.
    }

    /**
     * Bootstrap application services. Override bootPackage() for custom boot
     * logic. Laravel's base ServiceProvider::boot() is empty, so there is no
     * parent::boot() to call.
     */
    public function boot(): void
    {
        $this->bootPackage();
    }

    /**
     * Orchestrate package booting: fire bootingPackage(), boot all package
     * features (assets, views, routes, and so on), then fire packageBooted().
     *
     * Override to customize the flow; call parent::bootPackage() to keep the
     * default behavior.
     */
    public function bootPackage(): void
    {
        $this->bootingPackage();

        $this
            ->bootPackageAssets()
            ->bootPackageBladeComponents()
            ->bootPackageBladeDirectives()
            ->bootPackageCommands()
            ->bootPackageConsoleCommands()
            ->bootPackageConfigs()
            ->bootPackageInertia()
            ->bootPackageLivewireComponents()
            ->bootPackageMigrations()
            ->bootPackageRoutes()
            ->bootPackageServiceProviders()
            ->bootPackageTranslations()
            ->bootPackageViews()
            ->bootPackageViewComposers()
            ->bootPackageViewSharedData()
            ->bootPackageCustomPublishes()
            ->bootPackageDeferredHooks()
            ->packageBooted();
    }

    /**
     * Drive the boot-time hooks that live on the Package object itself
     * (middleware, event listeners, factories, seeders). Their
     * `bootPackage*` methods aren't part of the Process* chain above, so the
     * provider dispatches them explicitly.
     */
    protected function bootPackageDeferredHooks(): static
    {
        $router = $this->app['router'] ?? null;
        if ($router !== null) {
            $this->package->bootPackageMiddleware($router);
        }

        $this->package->bootPackageEventListeners();
        $this->package->bootPackageEventSubscribers();
        $this->package->bootPackageFactories();
        $this->package->bootPackageSeeders();

        return $this;
    }

    /**
     * Hook called before package boot begins. Override for setup that must
     * run before boot: early middleware, route bindings, and services that
     * need to be ready beforehand.
     */
    public function bootingPackage(): void
    {
        // Override in child class to add custom pre-boot logic.
    }

    /**
     * Hook called after package boot completes. Override for bindings and
     * event listeners that depend on booted services, and for finalizing
     * package setup.
     */
    public function packageBooted(): void
    {
        // Override in child class to add custom post-boot logic.
    }

    protected function getPackageBaseDir(): string
    {
        $reflector = new ReflectionClass(static::class);

        $fileName = $reflector->getFileName();
        if ($fileName === false) {
            throw new RuntimeException('Unable to resolve the service provider file path via reflection.');
        }

        $packageBaseDir = dirname($fileName);

        // Some packages like to keep Laravel's directory structure and place
        // the service providers in a Providers folder. Step up out of it.
        if (Str::endsWith($packageBaseDir, DIRECTORY_SEPARATOR . 'Providers')) {
            $packageBaseDir = dirname($packageBaseDir);
        }

        // When the PHP source lives under a src/ directory, the package root —
        // where resources/, database/, routes/ and config/ live — is the level
        // above it. Step up so resource paths resolve against the package root
        // rather than the source root.
        if (Str::endsWith($packageBaseDir, DIRECTORY_SEPARATOR . 'src')) {
            return dirname($packageBaseDir);
        }

        return $packageBaseDir;
    }

    public function packageView(?string $namespace): ?string
    {
        return is_null($namespace)
            ? $this->package->shortName()
            : $this->package->viewNamespace;
    }

    /**
     * Boot custom publish paths registered via $package->publish(),
     * including cleanup of destinations marked for it.
     */
    protected function bootPackageCustomPublishes(): static
    {
        if (! $this->app->runningInConsole()) {
            return $this;
        }

        $publishPaths = $this->package->getPublishPaths();

        if ($publishPaths === []) {
            return $this;
        }

        $pathsToClean = $this->package->getPublishPathsToClean();

        foreach ($publishPaths as $tag => $paths) {
            if (isset($pathsToClean[$tag]) && $pathsToClean[$tag]) {
                foreach ($paths as $destination) {
                    if (File::isDirectory($destination)) {
                        File::deleteDirectory($destination);
                    } elseif (File::exists($destination)) {
                        File::delete($destination);
                    }
                }
            }

            $this->publishes($paths, $tag);
        }

        return $this;
    }
}
