<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature;

use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Route;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Enums\DeprecationNotice;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\Package\Tools\Support\Routing\BareRouteNameAliases;

/**
 * hasDeprecatedRouteNames() on the Package must install the shared fallback through the provider's
 * boot chain, and hand the installed instance back so the package can ask has() / currentIs().
 */
final class BootPackageDeprecatedRouteNamesTest extends TestCase
{
    public function test_the_declared_bare_names_resolve_through_the_boot_chain(): void
    {
        Route::get('/legacy/page', static fn (): string => 'page')->name('laranail-legacy-routes.page');
        app('router')->getRoutes()->refreshNameLookups();

        $this->assertSame(route('laranail-legacy-routes.page'), route('legacy-routes.page'));
        $this->assertSame(route('laranail-legacy-routes.page'), route('old-page'));
    }

    public function test_the_installed_instance_is_exposed_on_the_package(): void
    {
        Route::get('/legacy/page', static fn (): string => 'page')->name('laranail-legacy-routes.page');
        app('router')->getRoutes()->refreshNameLookups();

        $aliases = DeprecatedRouteNamesTestPackageProvider::$configured?->deprecatedRouteNames();

        $this->assertInstanceOf(BareRouteNameAliases::class, $aliases);
        $this->assertSame('laranail/legacy-routes', $aliases->package());
        $this->assertTrue($aliases->has('legacy-routes.page'));
    }

    public function test_a_package_that_declares_none_installs_nothing(): void
    {
        $package = new Package;

        $this->assertNull($package->deprecatedRouteNames());
        $this->assertSame([], $package->getDeprecatedRouteNames());
    }

    protected function getPackageProviders($app): array
    {
        return [DeprecatedRouteNamesTestPackageProvider::class];
    }
}

final class DeprecatedRouteNamesTestPackageProvider extends PackageServiceProvider
{
    public static ?Package $configured = null;

    public function configurePackage(Package $package): void
    {
        $package->setName('laranail/legacy-routes');
        $package->basePath = sys_get_temp_dir();

        $package->hasDeprecatedRouteNames(
            map: ['old-page' => 'laranail-legacy-routes.page'],
            prefixes: ['legacy-routes.' => 'laranail-legacy-routes.'],
            notice: DeprecationNotice::None,
        );

        self::$configured = $package;
    }
}
