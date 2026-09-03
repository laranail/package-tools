<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature;

use Orchestra\Testbench\TestCase;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\Facades\Facade;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;

class WidgetManager {}

final class WidgetFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'widget';
    }
}

final class Gadget extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'gadget';
    }
}

final class AliasDerivationProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('acme/aliases')
            // Class named XFacade: the alias people write is X.
            ->hasFacade('widget', WidgetManager::class, WidgetFacade::class)
            // Class not named XFacade: the alias is the class name.
            ->hasFacade('gadget', WidgetManager::class, Gadget::class);
    }
}

/**
 * Naming a facade class `XFacade` is a common convention precisely because `X`
 * is the name callers write. Deriving the alias verbatim from the class name
 * gives `XFacade`, and the failure is silent - unqualified `X::` calls report a
 * missing class far from the registration.
 */
final class FacadeAliasDerivationTest extends TestCase
{
    public function test_a_trailing_facade_suffix_is_stripped(): void
    {
        $aliases = AliasLoader::getInstance()->getAliases();

        $this->assertArrayHasKey('Widget', $aliases);
        $this->assertSame(WidgetFacade::class, $aliases['Widget']);
        $this->assertArrayNotHasKey('WidgetFacade', $aliases);
    }

    public function test_a_class_not_ending_in_facade_keeps_its_name(): void
    {
        $this->assertSame(Gadget::class, AliasLoader::getInstance()->getAliases()['Gadget'] ?? null);
    }

    protected function getPackageProviders($app): array
    {
        return [AliasDerivationProvider::class];
    }
}
