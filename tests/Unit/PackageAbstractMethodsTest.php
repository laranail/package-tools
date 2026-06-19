<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Simtabi\Laranail\PackageTools\Package;

/**
 * Tests for abstract method implementations
 *
 * Verifies all required abstract methods from concerns are properly implemented
 */
class PackageAbstractMethodsTest extends TestCase
{
    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();
        $this->package = new Package;
        $this->package->setName('test-vendor/test-package');
        $this->package->setPathFrom('/var/www/package');
    }

    #[Test]
    public function it_implements_get_view_namespace(): void
    {
        $this->package->hasViews('custom-namespace');

        $reflection = new ReflectionClass($this->package);
        $method = $reflection->getMethod('getViewNamespace');

        $result = $method->invoke($this->package);

        $this->assertSame('custom-namespace', $result);
    }

    #[Test]
    public function it_implements_get_config_namespace(): void
    {
        $this->package->setName('vendor/test-package');

        $reflection = new ReflectionClass($this->package);
        $method = $reflection->getMethod('getConfigNamespace');

        $result = $method->invoke($this->package);

        $this->assertSame('vendor.test-package', $result);
    }

    #[Test]
    public function it_implements_get_package_kebab_name(): void
    {
        $this->package->setName('test-vendor/MyAwesomePackage');

        $reflection = new ReflectionClass($this->package);
        $method = $reflection->getMethod('getPackageKebabName');

        $result = $method->invoke($this->package);

        $this->assertSame('my-awesome-package', $result);
    }

    #[Test]
    public function it_implements_package_base_path(): void
    {
        $reflection = new ReflectionClass($this->package);
        $method = $reflection->getMethod('packageBasePath');

        $result = $method->invoke($this->package, 'subdirectory');

        $this->assertStringContainsString('subdirectory', $result);
        $this->assertStringStartsWith('/var/www/package', $result);
    }

    #[Test]
    public function it_implements_publish_asset_paths(): void
    {
        $reflection = new ReflectionClass($this->package);
        $method = $reflection->getMethod('publishAssetPaths');

        $paths = ['source' => 'destination'];
        $method->invoke($this->package, $paths, 'test-tag');

        // Verify paths were stored
        $property = $reflection->getProperty('assetPaths');
        $stored = $property->getValue($this->package);

        $this->assertArrayHasKey('test-tag', $stored);
        $this->assertSame($paths, $stored['test-tag']);
    }

    #[Test]
    public function it_implements_publishes(): void
    {
        $reflection = new ReflectionClass($this->package);
        $method = $reflection->getMethod('publishes');

        $paths = ['source' => 'destination'];
        $method->invoke($this->package, $paths, 'test-tag');

        // Verify paths were stored
        $property = $reflection->getProperty('publishPaths');
        $stored = $property->getValue($this->package);

        $this->assertArrayHasKey('test-tag', $stored);
        $this->assertSame($paths, $stored['test-tag']);
    }

    #[Test]
    public function it_implements_register_component_namespace(): void
    {
        $reflection = new ReflectionClass($this->package);
        $method = $reflection->getMethod('registerComponentNamespace');

        $method->invoke($this->package, 'App\\Components', 'app');

        // Verify namespace was stored
        $property = $reflection->getProperty('componentNamespaces');
        $stored = $property->getValue($this->package);

        $this->assertArrayHasKey('App\\Components', $stored);
        $this->assertSame('app', $stored['App\\Components']);
    }

    #[Test]
    public function it_has_get_dashed_namespace_from_trait(): void
    {
        $this->package->setName('vendor/test-package');

        $result = $this->package->getDashedNamespace();

        $this->assertSame('vendor-test-package', $result);
    }
}
