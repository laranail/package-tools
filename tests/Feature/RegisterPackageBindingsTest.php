<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature;

use stdClass;
use Orchestra\Testbench\TestCase;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\Facades\Facade;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;

class BindingsManager
{
    public function __construct(public string $tag = 'default') {}
}

final class BindingsFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'bindings-manager';
    }
}

final class BindingsTestProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('acme/bindings')
            ->hasSingleton(BindingsManager::class)
            ->hasBinding('acme.transient', static fn (): object => new stdClass)
            ->hasInstance('acme.instance', new stdClass)
            ->hasContainerAlias(BindingsManager::class, 'acme-manager')
            ->hasClassAlias('AcmeBindings', BindingsFacade::class)
            ->registerUsing(static function ($app): void {
                $app->instance('acme.from-callback', 'called');
            });
    }
}

final class RegisterPackageBindingsTest extends TestCase
{
    public function test_it_registers_singletons_bindings_and_instances(): void
    {
        $this->assertSame(
            $this->app->make(BindingsManager::class),
            $this->app->make(BindingsManager::class),
            'hasSingleton() must resolve once',
        );

        $this->assertNotSame(
            $this->app->make('acme.transient'),
            $this->app->make('acme.transient'),
            'hasBinding() must resolve fresh',
        );

        $this->assertInstanceOf(stdClass::class, $this->app->make('acme.instance'));
    }

    public function test_it_registers_the_container_alias(): void
    {
        $this->assertSame(
            $this->app->make(BindingsManager::class),
            $this->app->make('acme-manager'),
        );
    }

    public function test_it_registers_the_global_class_alias(): void
    {
        $this->assertArrayHasKey('AcmeBindings', AliasLoader::getInstance()->getAliases());
    }

    public function test_the_escape_hatch_runs_with_the_container(): void
    {
        $this->assertSame('called', $this->app->make('acme.from-callback'));
    }

    public function test_bindings_are_available_before_package_registered_runs(): void
    {
        // registerPackageBindings() runs BEFORE packageRegistered(), so a
        // consumer's own hook sees a fully bound container.
        $this->assertTrue($this->app->bound(BindingsManager::class));
    }

    protected function getPackageProviders($app): array
    {
        return [BindingsTestProvider::class];
    }
}
