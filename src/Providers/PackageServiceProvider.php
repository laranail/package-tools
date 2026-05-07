<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Providers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Override;
use ReflectionClass;
use Simtabi\Laranail\PackageTools\Concerns\PackageServiceProvider\LoadsHelpers;
use Simtabi\Laranail\PackageTools\Concerns\PackageServiceProvider\ProcessAssets;
use Simtabi\Laranail\PackageTools\Concerns\PackageServiceProvider\ProcessBladeComponents;
use Simtabi\Laranail\PackageTools\Concerns\PackageServiceProvider\ProcessBladeDirectives;
use Simtabi\Laranail\PackageTools\Concerns\PackageServiceProvider\ProcessCommands;
use Simtabi\Laranail\PackageTools\Concerns\PackageServiceProvider\ProcessConfigs;
use Simtabi\Laranail\PackageTools\Concerns\PackageServiceProvider\ProcessInertia;
use Simtabi\Laranail\PackageTools\Concerns\PackageServiceProvider\ProcessLivewireComponents;
use Simtabi\Laranail\PackageTools\Concerns\PackageServiceProvider\ProcessMigrations;
use Simtabi\Laranail\PackageTools\Concerns\PackageServiceProvider\ProcessRoutes;
use Simtabi\Laranail\PackageTools\Concerns\PackageServiceProvider\ProcessServiceProviders;
use Simtabi\Laranail\PackageTools\Concerns\PackageServiceProvider\ProcessTranslations;
use Simtabi\Laranail\PackageTools\Concerns\PackageServiceProvider\ProcessViewComposers;
use Simtabi\Laranail\PackageTools\Concerns\PackageServiceProvider\ProcessViews;
use Simtabi\Laranail\PackageTools\Concerns\PackageServiceProvider\ProcessViewSharedData;
use Simtabi\Laranail\PackageTools\Exceptions\InvalidPackage;
use Simtabi\Laranail\PackageTools\Exceptions\InvalidPath;
use Simtabi\Laranail\PackageTools\Package;

/**
 * PackageServiceProvider - Base Service Provider for Laravel Packages.
 *
 * This abstract class provides a structured approach to Laravel package development.
 * It enforces proper lifecycle management and provides hooks for infrastructure setup,
 * service registration, and boot-time operations.
 *
 * ## Quick Reference
 *
 * | Hook                  | Phase    | Use For                              |
 * |-----------------------|----------|--------------------------------------|
 * | registeringPackage()  | Register | Log channels, Horizon, morph maps    |
 * | configurePackage()    | Register | Package name, paths, features        |
 * | packageRegistered()   | Register | Singletons, bindings, aliases        |
 * | bootingPackage()      | Boot     | Middleware, Blade namespaces         |
 * | packageBooted()       | Boot     | Events, schedulers, finalization     |
 *
 * ## Lifecycle Overview
 *
 * Laravel service providers execute in two distinct phases. ALL providers complete
 * their registration phase before ANY provider begins booting.
 *
 * **Registration Phase** (All providers register before any boot):
 * - Provider A: register() → Provider B: register() → Provider C: register()
 *
 * Within each provider's register():
 * 1. `registeringPackage()` - Infrastructure (logs, queues, morph maps)
 * 2. `configurePackage()` - Package definition (name, paths, features)
 * 3. `registerPackageConfigs()` - Merge config files into Laravel config
 * 4. `packageRegistered()` - Service container bindings and singletons
 *
 * **Boot Phase** (Only after ALL providers registered):
 * - Provider A: boot() → Provider B: boot() → Provider C: boot()
 *
 * Within each provider's boot():
 * 1. `bootingPackage()` - Pre-boot setup (middleware, route bindings)
 * 2. `bootPackage*()` methods - Auto: views, routes, commands, migrations
 * 3. `packageBooted()` - Post-boot (events, schedulers, finalization)
 *
 * ## Placement Guide
 *
 * ### registeringPackage() - Infrastructure Layer
 *
 * **When:** First, before ANYTHING else in your package.
 * **Why:** These resources must exist before any service tries to use them.
 *
 * **Put here:**
 * - Log channel registration (services need channels to log to)
 * - Horizon queue supervisors (must exist before queue jobs dispatch)
 * - Eloquent morph maps (required for polymorphic relations)
 * - Child service provider registration (ensures proper loading order)
 * - Environment/capability detection (early decisions based on environment)
 *
 * **Do NOT put here:**
 * - Singletons that depend on config (config not yet loaded)
 * - Route definitions (belongs in boot phase)
 * - Event listeners (services may not be ready)
 *
 * ### packageRegistered() - Service Layer
 *
 * **When:** After configurePackage() and config files are merged.
 * **Why:** Package config is now available, infrastructure is ready.
 *
 * **Put here:**
 * - Singleton services (can now use config values)
 * - Service container bindings (Interface → Implementation)
 * - Aliases for services ('my-package.service' aliases)
 * - Facades backing classes
 * - Deferred service providers
 *
 * **Do NOT put here:**
 * - Log channels (too late, services already registered)
 * - Route/view operations (belongs in boot phase)
 * - Things needing other packages (other packages not fully registered yet)
 *
 * ### bootingPackage() - Pre-Boot Layer
 *
 * **When:** Start of boot phase, before automatic boot methods.
 * **Why:** Setup that must happen before routes/views/commands are registered.
 *
 * **Put here:**
 * - Middleware registration (must exist before routes load)
 * - Route model bindings (required for route parameters)
 * - Blade component namespaces (required before views compile)
 * - API versioning setup (before API routes register)
 * - Gate/policy registration (authorization must be ready)
 *
 * **Do NOT put here:**
 * - Service registration (too late, should be in register phase)
 * - Heavy operations (slows down every request)
 *
 * ### packageBooted() - Finalization Layer
 *
 * **When:** Last, after all automatic boot methods complete.
 * **Why:** Everything is ready - routes, views, services, other packages.
 *
 * **Put here:**
 * - Event listeners (all services ready to handle events)
 * - Scheduler task registration (commands are now available)
 * - Asset/icon registration (file systems ready)
 * - Cache warming (pre-populate caches if needed)
 * - Cross-package integrations (other packages are now booted)
 * - Debug/development tools
 *
 * **Safe to do here:**
 * - Access other package services (all packages are fully booted)
 * - Log messages (log channels are registered)
 * - Database operations (only if migrations have run)
 *
 * ## Code Examples
 *
 * ### Example 1: Registering Log Channels (MUST be in registeringPackage)
 *
 * ```php
 * public function registeringPackage(): void
 * {
 *     $this->app['config']->set('logging.channels.my-package', [
 *         'driver'     => 'daily',
 *         'path'       => storage_path('logs/my-package.log'),
 *         'level'      => env('MY_PACKAGE_LOG_LEVEL', 'info'),
 *         'days'       => 7,
 *         'permission' => 0644,
 *     ]);
 * }
 * ```
 *
 * ### Example 2: Horizon Queue Supervisor (MUST be in registeringPackage)
 *
 * ```php
 * public function registeringPackage(): void
 * {
 *     if (!class_exists(\Laravel\Horizon\Horizon::class)) {
 *         return;
 *     }
 *
 *     $this->app->booted(function () {
 *         config(['horizon.defaults.supervisor-my-package' => [
 *             'connection' => 'redis',
 *             'queue'      => ['my-package-queue'],
 *             'balance'    => 'simple',
 *             'processes'  => 3,
 *             'timeout'    => 600,
 *         ]]);
 *     });
 * }
 * ```
 *
 * ### Example 3: Service Registration (packageRegistered)
 *
 * ```php
 * public function packageRegistered(): void
 * {
 *     $this->app->singleton(MyLogger::class);
 *     $this->app->alias(MyLogger::class, 'my-package.logger');
 *
 *     $this->app->singleton(MyService::class, function ($app) {
 *         return new MyService($app->make(MyLogger::class));
 *     });
 * }
 * ```
 *
 * ### Example 4: Middleware Registration (bootingPackage)
 *
 * ```php
 * public function bootingPackage(): void
 * {
 *     $router = $this->app['router'];
 *     $router->aliasMiddleware('my-package.auth', MyAuthMiddleware::class);
 *     $router->middlewareGroup('my-package', ['web', 'my-package.auth']);
 * }
 * ```
 *
 * ### Example 5: Event Listeners and Schedulers (packageBooted)
 *
 * ```php
 * public function packageBooted(): void
 * {
 *     Event::listen(MyEvent::class, MyListener::class);
 *
 *     $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
 *         $schedule->command('my-package:cleanup')->daily()->at('03:00');
 *     });
 * }
 * ```
 *
 * ## Parent/Child Package Architecture
 *
 * When building package ecosystems (parent + child packages), timing is critical.
 *
 * **Registration Order** (determined by composer.json "extra.laravel.providers"):
 * - Parent registers: Log channels, core services, shared infrastructure
 * - Child A registers: Its own services (can reference parent's services)
 * - Child B registers: Its own services (can reference parent's services)
 *
 * **Boot Order:**
 * - Parent boots: Middleware, routes, views
 * - Child A boots: Can safely use parent's logger, services, routes
 * - Child B boots: Can safely use parent's logger, services, routes
 *
 * **Parent Package Responsibilities:**
 * - Register shared log channels in registeringPackage()
 * - Register shared services in packageRegistered()
 * - Provide base service class for children to extend
 *
 * **Child Package Responsibilities:**
 * - Do NOT register log channels (use parent's)
 * - Use parent's logger service for logging
 * - Log only in boot phase (bootingPackage or packageBooted)
 *
 * ## Anti-Patterns to Avoid
 *
 * **Wrong:** Logging during registration (log channel may not exist)
 * ```php
 * public function registeringPackage(): void
 * {
 *     Log::channel('my-package')->info('Starting...'); // FAILS!
 * }
 * ```
 *
 * **Wrong:** Registering log channels in bootingPackage (too late)
 * ```php
 * public function bootingPackage(): void
 * {
 *     $this->app['config']->set('logging.channels.my-package', [...]); // TOO LATE
 * }
 * ```
 *
 * **Wrong:** Heavy operations in boot methods
 * ```php
 * public function bootingPackage(): void
 * {
 *     $this->preloadAllIcons(); // Runs on EVERY request!
 * }
 * ```
 *
 * ## Debugging Tips
 *
 * 1. **"Log [channel] is not defined" error**
 *    - Move log channel registration to registeringPackage()
 *    - Ensure parent package is loaded before child packages
 *
 * 2. **Service not found during registration**
 *    - Move service usage to boot phase
 *    - Check provider loading order in composer.json
 *
 * 3. **Config values are null**
 *    - Access config in packageRegistered() or later
 *    - Use env() for early access, config() for later access
 *
 * 4. **Routes not loading**
 *    - Ensure hasRoutes() is called in configurePackage()
 *    - Check route files exist in the expected location
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
     * Register any application services.
     *
     * This method automatically handles package registration.
     * Override registerPackage() if you need custom registration logic.
     *
     * Note: Laravel's base ServiceProvider::register() is empty, so we don't need to call parent::register().
     *
     * @throws InvalidPackage|InvalidPath
     */
    #[Override]
    public function register(): void
    {
        // Laravel's base ServiceProvider::register() is empty, so we can skip parent::register()
        // Call our package registration directly
        $this->registerPackage();
    }

    /**
     * Register package services.
     *
     * This method orchestrates the entire package registration process:
     * 1. Calls registeringPackage() hook (before registration)
     * 2. Creates and configures the Package instance
     * 3. Validates package configuration
     * 4. Loads helpers and registers configs
     * 5. Calls packageRegistered() hook (after registration)
     *
     * **Override this method** if you need to completely customize the registration flow.
     * **Call parent::registerPackage()** if you want to keep default behavior and add custom logic.
     *
     * @throws InvalidPackage|InvalidPath
     *
     * @example
     * ```php
     * protected function registerPackage(): void
     * {
     *     // Custom pre-registration logic
     *     $this->setupCustomServices();
     *
     *     // Call parent to get default registration behavior
     *     parent::registerPackage();
     *
     *     // Custom post-registration logic
     *     $this->registerCustomBindings();
     * }
     * ```
     */
    public function registerPackage(): void
    {
        // Hook: Called before package registration starts
        $this->registeringPackage();

        // Create and configure package instance
        $this->package = $this->newPackage();
        $this->package->setPathFrom($this->getPackageBaseDir());

        // Let child class configure the package
        $this->configurePackage($this->package);

        // Validate package name is set and not empty
        if ($this->package->name === '' || $this->package->name === '0') {
            throw InvalidPackage::nameIsRequired();
        }

        // Validate basePath is set and not empty
        if ($this->package->basePath === '' || $this->package->basePath === '0') {
            throw InvalidPath::pathIsRequired();
        }

        // Auto-load helper functions if helpers/ directory exists
        $this->loadPackageHelpers();

        // Register package configs
        $this->registerPackageConfigs();

        // Hook: Called after package registration completes
        $this->packageRegistered();
    }

    /**
     * Hook: Called before package registration begins.
     *
     * Override this method to perform setup tasks before the package is registered.
     * This is the perfect place for:
     * - Setting up environment-specific configurations
     * - Registering service container bindings that don't depend on the package
     * - Initializing shared resources
     *
     *
     * @example
     * ```php
     * public function registeringPackage(): void
     * {
     *     // Set up environment-specific config
     *     if ($this->app->environment('local')) {
     *         $this->enableDebugMode();
     *     }
     *
     *     // Register early bindings
     *     $this->app->singleton('my-package.early-service', function() {
     *         return new EarlyService();
     *     });
     * }
     * ```
     */
    public function registeringPackage(): void
    {
        // Override in child class to add custom pre-registration logic
    }

    /**
     * Create a new Package instance.
     *
     * Override this method if you need to use a custom Package subclass
     * or inject dependencies into the Package instance.
     *
     *
     * @example
     * ```php
     * public function newPackage(): Package
     * {
     *     // Use custom Package subclass
     *     return new CustomPackage();
     *
     *     // Or inject dependencies
     *     return app(CustomPackage::class);
     * }
     * ```
     */
    public function newPackage(): Package
    {
        return new Package;
    }

    /**
     * Hook: Called after package registration completes.
     *
     * Override this method to perform tasks after the package is fully registered.
     * This is the perfect place for:
     * - Registering service container bindings that depend on the package
     * - Setting up event listeners
     * - Initializing package-specific services
     * - Post-registration validation
     *
     *
     * @example
     * ```php
     * public function packageRegistered(): void
     * {
     *     // Register services that depend on package configuration
     *     $this->app->singleton('my-package.service', function($app) {
     *         return new MyService($this->package);
     *     });
     *
     *     // Set up event listeners
     *     Event::listen(MyEvent::class, MyListener::class);
     *
     *     // Post-registration validation
     *     $this->validatePackageConfiguration();
     * }
     * ```
     */
    public function packageRegistered(): void
    {
        // Override in child class to add custom post-registration logic
    }

    /**
     * Bootstrap any application services.
     *
     * This method automatically handles package booting.
     * Override bootPackage() if you need custom boot logic.
     *
     * Note: Laravel's base ServiceProvider::boot() is empty, so we don't need to call parent::boot().
     */
    public function boot(): void
    {
        // Laravel's base ServiceProvider::boot() is empty, so we can skip parent::boot()
        // Call our package boot directly
        $this->bootPackage();
    }

    /**
     * Boot package services.
     *
     * This method orchestrates the entire package booting process:
     * 1. Calls bootingPackage() hook (before boot)
     * 2. Boots all package features (assets, views, routes, etc.)
     * 3. Calls packageBooted() hook (after boot)
     *
     * **Override this method** if you need to completely customize the boot flow.
     * **Call parent::bootPackage()** if you want to keep default behavior and add custom logic.
     *
     *
     * @example
     * ```php
     * protected function bootPackage(): void
     * {
     *     // Custom pre-boot logic
     *     $this->setupCustomBootServices();
     *
     *     // Call parent to get default boot behavior
     *     parent::bootPackage();
     *
     *     // Custom post-boot logic
     *     $this->registerCustomBootBindings();
     * }
     * ```
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
     * (middleware, event listeners, factories, seeders). These exist because
     * the corresponding Has* leaf traits were wired into Package by ADR-004
     * but their `bootPackage*` methods aren't part of the Process* chain
     * above — they need an explicit dispatch from the provider.
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
     * Hook: Called before package boot begins.
     *
     * Override this method to perform setup tasks before the package is booted.
     * This is the perfect place for:
     * - Setting up routes that need early registration
     * - Configuring middleware before boot
     * - Initializing services that need to be ready before boot
     *
     *
     * @example
     * ```php
     * public function bootingPackage(): void
     * {
     *     // Register early middleware
     *     $this->app['router']->pushMiddlewareToGroup('web', MyMiddleware::class);
     *
     *     // Set up early route bindings
     *     Route::bind('custom-model', function($value) {
     *         return CustomModel::findOrFail($value);
     *     });
     * }
     * ```
     */
    public function bootingPackage(): void
    {
        // Override in child class to add custom pre-boot logic
    }

    /**
     * Hook: Called after package boot completes.
     *
     * Override this method to perform tasks after the package is fully booted.
     * This is the perfect place for:
     * - Registering service container bindings that depend on booted services
     * - Setting up event listeners that need booted services
     * - Finalizing package initialization
     * - Post-boot validation or setup
     *
     *
     * @example
     * ```php
     * public function packageBooted(): void
     * {
     *     // Register services that depend on booted package
     *     $this->app->singleton('my-package.booted-service', function($app) {
     *         return new BootedService($this->package);
     *     });
     *
     *     // Set up event listeners
     *     Event::listen(MyEvent::class, MyListener::class);
     *
     *     // Finalize package setup
     *     $this->finalizePackageSetup();
     * }
     * ```
     */
    public function packageBooted(): void
    {
        // Override in child class to add custom post-boot logic
    }

    protected function getPackageBaseDir(): string
    {
        $reflector = new ReflectionClass(static::class);

        $packageBaseDir = dirname($reflector->getFileName());

        // Some packages like to keep Laravels directory structure and place
        // the service providers in a Providers folder.
        // move up a level when this is the case.
        if (Str::endsWith($packageBaseDir, DIRECTORY_SEPARATOR . 'Providers')) {
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
     * Boot custom publish paths registered via $package->publish()
     *
     * This processes all custom publish paths that were registered using
     * the flexible publish() method, including cleanup support.
     */
    protected function bootPackageCustomPublishes(): static
    {
        if (! $this->app->runningInConsole()) {
            return $this;
        }

        // Get custom publish paths using public getter method
        $publishPaths = $this->package->getPublishPaths();

        if ($publishPaths === []) {
            return $this;
        }

        // Get tags that need cleanup
        $pathsToClean = $this->package->getPublishPathsToClean();

        // Process each publish tag
        foreach ($publishPaths as $tag => $paths) {
            // Clean destination if requested
            if (isset($pathsToClean[$tag]) && $pathsToClean[$tag]) {
                // Clean each destination path
                foreach ($paths as $destination) {
                    if (is_dir($destination)) {
                        File::deleteDirectory($destination);
                    } elseif (file_exists($destination)) {
                        File::delete($destination);
                    }
                }
            }

            // Publish the paths
            $this->publishes($paths, $tag);
        }

        return $this;
    }
}
