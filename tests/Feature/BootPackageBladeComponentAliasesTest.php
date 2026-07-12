<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\View\Component;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\Package\Tools\Providers\PackageToolsServiceProvider;

/**
 * hasBladeComponentAlias() and both component-namespace surfaces —
 * hasBladeComponentNamespace() and the legacy hasComponentNamespace()
 * (write-only before 2.0) — must reach the Blade compiler at boot.
 */
final class BootPackageBladeComponentAliasesTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        // PackageToolsServiceProvider supplies the ComponentNamespaceResolver
        // dependencies used by the legacy hasComponentNamespace() path
        return [PackageToolsServiceProvider::class, BladeComponentsTestPackageProvider::class];
    }

    public function test_component_alias_registers_with_the_blade_compiler(): void
    {
        $aliases = Blade::getClassComponentAliases();

        $this->assertArrayHasKey('pkg::thing', $aliases);
        $this->assertSame(BladeFixtureComponent::class, $aliases['pkg::thing']);
    }

    public function test_aliased_component_renders_through_the_compiler(): void
    {
        $html = Blade::render('<x-pkg::thing />');

        $this->assertStringContainsString('blade-fixture-rendered', $html);
    }

    public function test_blade_component_namespace_registers_with_the_blade_compiler(): void
    {
        $namespaces = Blade::getClassComponentNamespaces();

        $this->assertArrayHasKey('pkgns', $namespaces);
        $this->assertSame('Test\\Blade\\Components', $namespaces['pkgns']);
    }

    public function test_legacy_has_component_namespace_reaches_the_blade_compiler(): void
    {
        // hasComponentNamespace('modules/admin', 'legacy-admin') resolves the
        // class namespace through the ComponentNamespaceResolver's pattern
        // ({project}\{module}\{component_ns} with App/View\Components defaults)
        $namespaces = Blade::getClassComponentNamespaces();

        $this->assertArrayHasKey('legacy-admin', $namespaces);
        $this->assertSame('App\\Admin\\View\\Components', $namespaces['legacy-admin']);
    }
}

final class BladeFixtureComponent extends Component
{
    public function render(): string
    {
        return '<div>blade-fixture-rendered</div>';
    }
}

final class BladeComponentsTestPackageProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->setName('test/blade-components');
        $package->basePath = sys_get_temp_dir();

        $package->hasBladeComponentAlias('pkg::thing', BladeFixtureComponent::class);
        $package->hasBladeComponentNamespace('Test\\Blade\\Components', 'pkgns');
        $package->hasComponentNamespace('modules/admin', 'legacy-admin');
    }
}
