<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\TestCase;
use RuntimeException;
use Simtabi\Laranail\PackageTools\Package;
use Simtabi\Laranail\PackageTools\Providers\PackageServiceProvider;

/**
 * PackageTestCase - Base test case for testing packages built with Laranail Packager
 *
 * **IMPROVEMENT #5: Testing Documentation & Helpers**
 *
 * Provides a foundation for testing Laravel packages that use the Laranail Packager.
 * Includes helper methods for common testing scenarios.
 *
 * **Usage:**
 * Extend this class in your package's test suite and implement getPackageProviders().
 */
abstract class PackageTestCase extends TestCase
{
    /**
     * Get the package service provider class
     *
     * Override this method to return your package's service provider.
     *
     * @return class-string<PackageServiceProvider>
     *
     * @example
     * protected function getPackageServiceProviderClass(): string
     * {
     *     return MyPackageServiceProvider::class;
     * }
     */
    abstract protected function getPackageServiceProviderClass(): string;

    /**
     * Get package providers
     *
     * @param Application $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            $this->getPackageServiceProviderClass(),
        ];
    }

    /**
     * Get the configured package instance
     */
    protected function getPackage(): Package
    {
        $providerClass = $this->getPackageServiceProviderClass();
        $provider = new $providerClass($this->app);

        return $provider->package ?? throw new RuntimeException(
            'Package instance not found. Ensure your service provider extends PackageServiceProvider.'
        );
    }

    /**
     * Assert config file is registered
     *
     * @param string $configKey Config key to check
     * @param mixed $defaultValue Optional default value to check
     */
    protected function assertConfigRegistered(string $configKey, mixed $defaultValue = null): void
    {
        $this->assertTrue(
            config()->has($configKey),
            "Config key '{$configKey}' is not registered."
        );

        if ($defaultValue !== null) {
            $this->assertEquals(
                $defaultValue,
                config($configKey),
                "Config key '{$configKey}' does not have expected default value."
            );
        }
    }

    /**
     * Assert view exists
     *
     * @param string $view View name (e.g., 'package::component')
     */
    protected function assertViewExists(string $view): void
    {
        $this->assertTrue(
            view()->exists($view),
            "View '{$view}' does not exist."
        );
    }

    /**
     * Assert Blade component is registered
     *
     * @param string $componentName Component name (e.g., 'alert')
     * @param string $prefix Component prefix (e.g., 'package')
     */
    protected function assertBladeComponentRegistered(string $componentName, string $prefix = ''): void
    {
        $fullName = $prefix !== '' && $prefix !== '0' ? "{$prefix}::{$componentName}" : $componentName;

        $this->assertTrue(
            view()->exists("components.{$fullName}"),
            "Blade component '{$fullName}' is not registered."
        );
    }

    /**
     * Assert command exists
     *
     * @param string $commandName Command name or signature
     */
    protected function assertCommandExists(string $commandName): void
    {
        $commands = array_keys($this->app[Kernel::class]->all());

        $this->assertContains(
            $commandName,
            $commands,
            "Command '{$commandName}' is not registered."
        );
    }

    /**
     * Assert route is registered
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $uri Route URI
     */
    protected function assertRouteExists(string $method, string $uri): void
    {
        $routes = app('router')->getRoutes();
        $found = false;

        foreach ($routes as $route) {
            if (in_array(strtoupper($method), $route->methods()) && $route->uri() === ltrim($uri, '/')) {
                $found = true;
                break;
            }
        }

        $this->assertTrue(
            $found,
            "Route '{$method} {$uri}' is not registered."
        );
    }

    /**
     * Assert migration exists
     *
     * @param string $migrationName Migration file name (without timestamp)
     */
    protected function assertMigrationExists(string $migrationName): void
    {
        $migrationPath = database_path('migrations');
        $files = glob("{$migrationPath}/*{$migrationName}.php");

        $this->assertNotEmpty(
            $files,
            "Migration '{$migrationName}' does not exist in '{$migrationPath}'."
        );
    }

    /**
     * Assert translation key exists
     *
     * @param string $key Translation key (e.g., 'package::messages.welcome')
     * @param string|null $locale Optional locale to check
     */
    protected function assertTranslationExists(string $key, ?string $locale = null): void
    {
        $locale ??= app()->getLocale();

        $this->assertTrue(
            trans()->has($key, $locale),
            "Translation key '{$key}' does not exist for locale '{$locale}'."
        );
    }

    /**
     * Assert package namespace is configured correctly
     *
     * @param string $expectedVendor Expected vendor name
     * @param string $expectedPackage Expected package name
     */
    protected function assertPackageNamespace(string $expectedVendor, string $expectedPackage): void
    {
        $package = $this->getPackage();

        $this->assertEquals(
            $expectedVendor,
            $package->getConfigVendor(),
            'Package vendor does not match expected value.'
        );

        $this->assertEquals(
            $expectedPackage,
            $package->shortName(),
            'Package name does not match expected value.'
        );

        $this->assertEquals(
            "{$expectedVendor}.{$expectedPackage}",
            $package->getDottedNamespace(),
            'Package dotted namespace does not match expected value.'
        );
    }

    /**
     * Assert helper function exists
     *
     * @param string $functionName Helper function name
     */
    protected function assertHelperFunctionExists(string $functionName): void
    {
        $this->assertTrue(
            function_exists($functionName),
            "Helper function '{$functionName}' does not exist."
        );
    }

    /**
     * Assert publishable group exists
     *
     * @param string $group Publishable group name (e.g., 'package-config')
     */
    protected function assertPublishableGroupExists(string $group): void
    {
        $publishables = $this->app[ServiceProvider::class]::$publishes;

        $found = false;
        foreach ($publishables as $paths) {
            if (isset($paths[$group]) || array_key_exists($group, $paths)) {
                $found = true;
                break;
            }
        }

        $this->assertTrue(
            $found,
            "Publishable group '{$group}' is not registered."
        );
    }

    /**
     * Load package migrations for testing
     */
    protected function loadPackageMigrations(): void
    {
        $package = $this->getPackage();
        $migrationPath = $package->basePath(Package::MIGRATIONS_DIR);

        if (is_dir($migrationPath)) {
            $this->loadMigrationsFrom($migrationPath);
        }
    }

    /**
     * Publish and load package migrations
     */
    protected function publishAndLoadMigrations(): void
    {
        $package = $this->getPackage();
        $packageName = $package->shortName();

        $this->artisan('vendor:publish', [
            '--tag' => "{$packageName}-migrations",
            '--force' => true,
        ]);

        $this->loadMigrationsFrom(database_path('migrations'));
    }

    /**
     * Get config from namespaced package
     *
     * Helper to retrieve config with automatic namespace handling.
     *
     * @param string $key Config key (without namespace)
     * @param mixed $default Default value
     */
    protected function getPackageConfig(string $key, mixed $default = null): mixed
    {
        $package = $this->getPackage();
        $configKey = $package->hasConfigNamespacing()
            ? $package->getNamespacedConfigKey($key)
            : $key;

        return config($configKey, $default);
    }

    /**
     * Set config for namespaced package
     *
     * Helper to set config with automatic namespace handling.
     *
     * @param string $key Config key (without namespace)
     * @param mixed $value Config value
     */
    protected function setPackageConfig(string $key, mixed $value): void
    {
        $package = $this->getPackage();
        $configKey = $package->hasConfigNamespacing()
            ? $package->getNamespacedConfigKey($key)
            : $key;

        config([$configKey => $value]);
    }
}
