<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Tests\Unit\Concerns;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\PackageTools\Package;

/**
 * Tests for HasRoutes concern
 */
class HasRoutesTest extends TestCase
{
    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();
        $this->package = new Package;
        $this->package->setName('test-vendor/test-package');
    }

    #[Test]
    public function it_can_register_single_route_file(): void
    {
        $result = $this->package->hasRoutes('web');

        $this->assertSame($this->package, $result, 'Should support fluent chaining');
        $this->assertContains('web', $this->package->routeFileNames);
    }

    #[Test]
    public function it_can_register_multiple_route_files_as_array(): void
    {
        $routes = ['web', 'api', 'channels'];

        $this->package->hasRoutes($routes);

        $this->assertCount(3, $this->package->routeFileNames);
        $this->assertSame($routes, $this->package->routeFileNames);
    }

    #[Test]
    public function it_can_register_multiple_route_files_as_string(): void
    {
        $this->package->hasRoutes('web');

        $this->assertContains('web', $this->package->routeFileNames);
    }

    #[Test]
    public function it_stores_route_files_as_array(): void
    {
        $this->package->hasRoutes('web');

        $this->assertIsArray($this->package->routeFileNames);
    }
}
