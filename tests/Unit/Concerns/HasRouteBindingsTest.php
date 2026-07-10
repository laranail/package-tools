<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Concerns;

use Illuminate\Foundation\Auth\User;
use Illuminate\Routing\Router;
use Orchestra\Testbench\TestCase;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Exceptions\PackageBootException;
use Simtabi\Laranail\Package\Tools\Package;

/**
 * HasRouteBindings: declarative Route::model / Route::bind, mirroring
 * bootPackageMiddleware(Router). Models may be a class-string or a Closure /
 * config key resolved at boot.
 */
final class HasRouteBindingsTest extends TestCase
{
    public function test_route_models_and_binders_are_registered_at_boot(): void
    {
        $router = $this->app->make(Router::class);

        $package = (new Package)->name('acme/x');
        $package->registerRouteModel('account', User::class);
        $package->registerRouteBinding('slug', static fn (string $value): string => "resolved:{$value}");

        $this->assertNull($router->getBindingCallback('account'));

        $package->bootPackageRouteBindings($router);

        $this->assertNotNull($router->getBindingCallback('account'));
        $this->assertNotNull($router->getBindingCallback('slug'));
    }

    public function test_a_model_read_from_config_resolves_at_boot(): void
    {
        config()->set('acme.models.user', User::class);
        $router = $this->app->make(Router::class);

        $package = (new Package)->name('acme/x');
        $package->registerRouteModelFromConfig('user', 'acme.models.user');
        $package->bootPackageRouteBindings($router);

        $this->assertNotNull($router->getBindingCallback('user'));
    }

    public function test_a_missing_model_class_is_skipped(): void
    {
        $router = $this->app->make(Router::class);

        $package = (new Package)->name('acme/x');
        $package->registerRouteModel('ghost', 'Acme\\Does\\Not\\Exist');
        $package->bootPackageRouteBindings($router);

        $this->assertNull($router->getBindingCallback('ghost'));
    }

    public function test_a_throwing_model_closure_fails_loud_with_an_annotated_exception(): void
    {
        $router = $this->app->make(Router::class);

        $package = (new Package)->name('acme/x');
        $package->registerRouteModel('broken', static function (): string {
            throw new RuntimeException('model resolution failed');
        });

        try {
            $package->bootPackageRouteBindings($router);
            $this->fail('a throwing route-model closure must fail loud');
        } catch (PackageBootException $e) {
            $this->assertStringContainsString('route model binding [broken]', $e->getMessage());
            $this->assertStringContainsString('model resolution failed', $e->getMessage());
        }
    }
}
