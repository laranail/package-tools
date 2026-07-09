<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\Package\Tools\Support\Definitions\RouteGroupDefinition;

final class RouteGroupGateTestProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('acme/routing-gated')
            ->setPathFrom(__DIR__ . '/../fixtures/route-group')
            ->registerRouteGroup(
                RouteGroupDefinition::make('routes/api.php')
                    ->prefix('api')
                    ->middleware(['api'])
                    ->name('acme.')
                    ->whenConfig('acme.features.api'),
            );
    }
}

/**
 * A route group whose whenConfig() gate is false is never registered.
 */
final class RouteGroupGateTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [RouteGroupGateTestProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('acme.features.api', false);
    }

    public function test_a_gated_off_group_is_absent(): void
    {
        $this->assertNull(Route::getRoutes()->getByName('acme.ping'));
    }
}
