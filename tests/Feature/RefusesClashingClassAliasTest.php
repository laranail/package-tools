<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature;

use Orchestra\Testbench\TestCase;
use Illuminate\Foundation\AliasLoader;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;

class TheirClass {}

class OurClass {}

final class ClashingAliasProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('acme/clashing')->hasClassAlias('Contested', OurClass::class);
    }
}

/**
 * Laravel's AliasLoader is a flat global map, so `alias()` replaces silently. A
 * package that overwrites the application's alias shadows it, and the damage
 * surfaces far away as the wrong class resolving.
 *
 * This is the same failure the whole naming convention exists to prevent, so
 * the package builder refuses rather than takes.
 */
final class RefusesClashingClassAliasTest extends TestCase
{
    public function test_it_does_not_overwrite_an_alias_the_application_already_claimed(): void
    {
        $this->assertSame(
            TheirClass::class,
            AliasLoader::getInstance()->getAliases()['Contested'],
            'the package overwrote an alias that was already taken',
        );
    }

    public function test_it_records_what_it_refused(): void
    {
        $provider = $this->app->getProviders(ClashingAliasProvider::class);

        $this->assertSame(
            ['Contested' => TheirClass::class],
            reset($provider)->refusedClassAliases,
            'a refusal must be visible, not silent',
        );
    }

    protected function getEnvironmentSetUp($app): void
    {
        // The application got there first.
        AliasLoader::getInstance(['Contested' => TheirClass::class])->register();
    }

    protected function getPackageProviders($app): array
    {
        return [ClashingAliasProvider::class];
    }
}
