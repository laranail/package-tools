<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature;

use Livewire\Livewire;
use Orchestra\Testbench\TestCase;
use Override;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;

/**
 * livewire is intentionally not in this package's dev dependencies, so
 * the primary contract under test is the guard: a package declaring
 * livewire components must boot cleanly when Livewire\Livewire does not
 * exist — registration is a silent no-op. the gate/reactive behaviors
 * need the real class (Livewire::component is called on it directly) and
 * cannot be simulated with a container stub, so those cases only run
 * when livewire is installed.
 */
final class BootPackageLivewireGateTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [LivewireGateTestPackageProvider::class];
    }

    public function test_declaring_livewire_components_without_livewire_installed_boots_cleanly(): void
    {
        if (class_exists(Livewire::class)) {
            $this->markTestSkipped('livewire is installed; the missing-class guard path is unreachable');
        }

        // setUp() already booted the provider — reaching this line means
        // bootPackageLivewireComponents() took the class_exists guard exit
        $this->assertFalse($this->app->bound('livewire'));

        $package = $this->app->make(LivewireGateTestPackageProvider::class)->package;

        $this->assertSame(
            ['gate-thing' => LivewireGateFixtureComponent::class],
            $package->livewireComponents,
            'the declaration itself must survive on the package object',
        );
    }

    public function test_component_declarations_record_the_prefix_opt_out(): void
    {
        // withoutLivewireNamespacePrefix() state is observable regardless of
        // whether livewire is installed
        $package = $this->app->make(LivewireGateTestPackageProvider::class)->package;

        $this->assertFalse($package->livewirePrefixComponents);
    }

    public function test_config_gate_off_prevents_component_registration(): void
    {
        if (! class_exists(Livewire::class)) {
            $this->markTestSkipped('requires livewire: the gate short-circuits before Livewire::component is reached');
        }

        config()->set('test.livewire_on', false);

        // would assert the component registry lacks 'gate-thing' here
        $this->fail('implement the gate-off assertion once livewire joins require-dev');
    }

    public function test_components_register_reactively_when_livewire_binds_after_boot(): void
    {
        if (! class_exists(Livewire::class)) {
            $this->markTestSkipped('requires livewire: afterResolving("livewire") needs the real manager to bind');
        }

        // would boot the package before binding 'livewire', then bind it and
        // assert the components registered afterwards
        $this->fail('implement the reactive-registration assertion once livewire joins require-dev');
    }
}

final class LivewireGateFixtureComponent
{
    // deliberately not a real livewire component: with livewire absent the
    // guard exits before the class is ever touched
}

final class LivewireGateTestPackageProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->setName('test/livewire-gate');
        $package->basePath = sys_get_temp_dir();

        $package
            ->hasLivewireComponents(
                ['gate-thing' => LivewireGateFixtureComponent::class],
                whenConfig: 'test.livewire_on',
            )
            ->withoutLivewireNamespacePrefix();
    }

    #[Override]
    public function register(): void
    {
        parent::register();

        $this->app->singleton(self::class, fn (): static => $this);
    }
}
