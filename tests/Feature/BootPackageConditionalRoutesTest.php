<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;

/**
 * hasRoutesWhen() must gate route-file loading on config at boot: the
 * gate is read in bootPackageRoutes(), so per-test config lands via
 * DefineEnvironment (which runs before the providers boot).
 */
final class BootPackageConditionalRoutesTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [ConditionalRoutesTestPackageProvider::class];
    }

    protected function enableExtraRoutes(Application $app): void
    {
        $app['config']->set('test.routes_on', true);
    }

    protected function disableDefaultOnRoutes(Application $app): void
    {
        $app['config']->set('test.routes_default_on', false);
    }

    #[DefineEnvironment('enableExtraRoutes')]
    public function test_conditional_routes_load_when_the_config_gate_is_on(): void
    {
        $this->assertTrue(Route::has('pkg.extra'));

        $this->get('/pkg-extra')->assertOk()->assertSee('extra');
    }

    public function test_conditional_routes_stay_absent_when_the_gate_is_off(): void
    {
        // 'test.routes_on' is never set and the declared default is false
        $this->assertFalse(Route::has('pkg.extra'));
    }

    public function test_a_true_default_loads_routes_without_any_config(): void
    {
        $this->assertTrue(Route::has('pkg.default-on'));
    }

    #[DefineEnvironment('disableDefaultOnRoutes')]
    public function test_explicit_config_overrides_a_true_default(): void
    {
        $this->assertFalse(Route::has('pkg.default-on'));
    }
}

final class ConditionalRoutesTestPackageProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->setName('test/conditional-routes');
        $package->basePath = dirname(__DIR__) . '/fixtures/routes-package';

        $package->hasRoutesWhen('test.routes_on', 'extra');
        $package->hasRoutesWhen('test.routes_default_on', 'on-by-default', default: true);
    }
}
