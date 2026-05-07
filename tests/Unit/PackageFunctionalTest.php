<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Simtabi\Laranail\PackageTools\Exceptions\InvalidPackage;
use Simtabi\Laranail\PackageTools\Exceptions\InvalidPath;
use Simtabi\Laranail\PackageTools\Package;

/**
 * Functional tests for Package class
 *
 * Tests the Package class works correctly with all concerns and methods
 */
class PackageFunctionalTest extends TestCase
{
    #[Test]
    public function it_can_create_and_configure_complete_package(): void
    {
        $package = new Package;

        $package
            ->setName('vendor/test-package')
            ->setPathFrom('/var/www/packages/test-package')
            ->hasConfigFile('config')
            ->hasViews('test-package')
            ->hasTranslations()
            ->hasMigrations()
            ->hasRoutes('web')
            ->hasCommands(['TestCommand']);

        $this->assertSame('test-package', $package->name);
        $this->assertSame('vendor', $package->configVendor);
        $this->assertSame('/var/www/packages/test-package', $package->basePath);
        $this->assertTrue($package->hasViews);
        $this->assertTrue($package->hasMigrations);
        $this->assertTrue($package->hasTranslations);
        $this->assertNotEmpty($package->configFileNames);
        $this->assertNotEmpty($package->routeFileNames);
        $this->assertNotEmpty($package->commands);
    }

    #[Test]
    public function it_enforces_name_validation(): void
    {
        $package = new Package;

        $this->expectException(InvalidPackage::class);
        $package->setName('');
    }

    #[Test]
    public function it_enforces_basepath_validation(): void
    {
        $package = new Package;

        $this->expectException(InvalidPath::class);
        $package->setPathFrom('');
    }

    #[Test]
    public function it_provides_all_namespace_formats(): void
    {
        $package = new Package;
        $package->setName('vendor/test-package');

        $this->assertSame('vendor.test-package', $package->getDottedNamespace());
        $this->assertSame('vendor-test-package', $package->getDashedNamespace());
        $this->assertSame('vendor::test-package', $package->getDoubleColonNamespace());
        $this->assertSame('vendor/test-package', $package->getSlashNamespace());
    }

    #[Test]
    public function it_provides_kebab_case_name(): void
    {
        $package = new Package;
        $package->setName('test-vendor/MyAwesomePackage');

        $reflection = new ReflectionClass($package);
        $method = $reflection->getMethod('getPackageKebabName');

        $result = $method->invoke($package);

        $this->assertSame('my-awesome-package', $result);
    }

    #[Test]
    public function it_handles_view_namespace_correctly(): void
    {
        $package = new Package;
        $package->setName('test-vendor/test-package');
        $package->hasViews('custom-namespace');

        $reflection = new ReflectionClass($package);
        $method = $reflection->getMethod('getViewNamespace');

        $result = $method->invoke($package);

        $this->assertSame('custom-namespace', $result);
    }

    #[Test]
    public function it_handles_config_namespace_correctly(): void
    {
        $package = new Package;
        $package->setName('vendor/test-package');

        $reflection = new ReflectionClass($package);
        $method = $reflection->getMethod('getConfigNamespace');

        $result = $method->invoke($package);

        $this->assertSame('vendor.test-package', $result);
    }

    #[Test]
    public function it_supports_fluent_chaining_with_all_methods(): void
    {
        $package = new Package;

        $result = $package
            ->setName('test-vendor/test-package')
            ->setPathFrom('/tmp')
            ->hasConfigFile()
            ->hasViews()
            ->hasTranslations()
            ->hasMigrations()
            ->hasRoutes('web')
            ->hasCommands(['Command']);

        $this->assertSame($package, $result);
    }

    #[Test]
    public function it_handles_package_base_path_with_subdirectory(): void
    {
        $package = new Package;
        $package->setPathFrom('/var/www/package');

        $reflection = new ReflectionClass($package);
        $method = $reflection->getMethod('packageBasePath');

        $result = $method->invoke($package, 'subdir');

        $this->assertStringContainsString('subdir', $result);
        $this->assertStringStartsWith('/var/www/package', $result);
    }

    #[Test]
    public function it_stores_asset_paths_for_publishing(): void
    {
        $package = new Package;
        $package->setName('test-vendor/test');
        $package->setPathFrom('/tmp');

        $reflection = new ReflectionClass($package);
        $method = $reflection->getMethod('publishAssets');

        $paths = ['source/path' => 'dest/path'];
        $method->invoke($package, $paths, 'assets-tag');

        $property = $reflection->getProperty('assetPaths');
        $stored = $property->getValue($package);

        $this->assertArrayHasKey('assets-tag', $stored);
        $this->assertSame($paths, $stored['assets-tag']);
    }

    #[Test]
    public function it_stores_component_namespaces(): void
    {
        $package = new Package;
        $package->setName('test-vendor/test');
        $package->setPathFrom('/tmp');

        $reflection = new ReflectionClass($package);
        $method = $reflection->getMethod('registerComponentNamespace');

        $method->invoke($package, 'App\\Components', 'app');

        $property = $reflection->getProperty('componentNamespaces');
        $stored = $property->getValue($package);

        $this->assertArrayHasKey('App\\Components', $stored);
        $this->assertSame('app', $stored['App\\Components']);
    }
}
