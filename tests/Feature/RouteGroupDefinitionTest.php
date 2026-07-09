<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\Package\Tools\Support\Definitions\RouteGroupDefinition;

final class RouteGroupTestProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('acme/routing')
            ->setPathFrom(__DIR__ . '/../fixtures/route-group')
            ->registerRouteGroup(
                RouteGroupDefinition::make('routes/api.php')
                    ->prefixFromConfig('acme.routes.api_prefix', 'api')
                    ->middlewareFromConfig('acme.routes.api_middleware', ['api'])
                    ->name('acme.')
                    ->whenConfig('acme.features.api'),
            );
    }
}

/**
 * Route groups: a route file wrapped in Route::middleware()->prefix()->group(),
 * with config-resolved middleware/prefix and a whenConfig gate.
 */
final class RouteGroupDefinitionTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [RouteGroupTestProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('acme.features.api', true);
    }

    public function test_the_group_registers_the_route_with_prefix_middleware_and_name(): void
    {
        $route = Route::getRoutes()->getByName('acme.ping');

        $this->assertInstanceOf(RoutingRoute::class, $route);
        $this->assertSame('api/ping', $route->uri());
        $this->assertContains('api', $route->gatherMiddleware());
    }
}
