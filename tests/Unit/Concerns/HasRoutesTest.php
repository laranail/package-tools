<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Concerns;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\Package\Tools\Package;

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

    #[Test]
    public function it_can_register_a_single_route_file_with_has_route(): void
    {
        $result = $this->package->hasRoute('console');

        $this->assertSame($this->package, $result, 'Should support fluent chaining');
        $this->assertSame(['console'], $this->package->routeFileNames);
    }

    #[Test]
    public function has_routes_when_stores_the_conditional_spec(): void
    {
        $result = $this->package->hasRoutesWhen('test-package.routes.enabled', 'api');

        $this->assertSame($this->package, $result, 'Should support fluent chaining');
        $this->assertSame([[
            'key' => 'test-package.routes.enabled',
            'files' => ['api'],
            'default' => false,
        ]], $this->package->conditionalRouteFileNames);
    }

    #[Test]
    public function has_routes_when_normalizes_a_file_list_and_keeps_the_default(): void
    {
        $this->package->hasRoutesWhen('test-package.routes.enabled', ['web', 'api'], true);

        $this->assertSame([[
            'key' => 'test-package.routes.enabled',
            'files' => ['web', 'api'],
            'default' => true,
        ]], $this->package->conditionalRouteFileNames);
    }

    #[Test]
    public function conditional_route_files_do_not_touch_the_unconditional_list(): void
    {
        $this->package->hasRoutesWhen('test-package.routes.enabled', 'api');

        $this->assertSame([], $this->package->routeFileNames);
    }
}
