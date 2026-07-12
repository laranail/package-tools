<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Concerns;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\Package\Tools\Package;

/**
 * Tests for HasBladeComponents concern
 */
class HasBladeComponentsTest extends TestCase
{
    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();
        $this->package = new Package;
        $this->package->setName('test-vendor/test-package');
    }

    #[Test]
    public function it_can_register_view_components(): void
    {
        $result = $this->package->hasViewComponents('prefix', 'Component1', 'Component2');

        $this->assertSame($this->package, $result, 'Should support fluent chaining');
        $this->assertArrayHasKey('Component1', $this->package->viewComponents);
        $this->assertArrayHasKey('Component2', $this->package->viewComponents);
        $this->assertSame('prefix', $this->package->viewComponents['Component1']);
    }

    #[Test]
    public function it_can_register_single_view_component(): void
    {
        $result = $this->package->hasViewComponent('prefix', 'SingleComponent');

        $this->assertSame($this->package, $result, 'Should support fluent chaining');
        $this->assertArrayHasKey('SingleComponent', $this->package->viewComponents);
        $this->assertSame('prefix', $this->package->viewComponents['SingleComponent']);
    }

    #[Test]
    public function it_can_register_a_blade_component_namespace(): void
    {
        $result = $this->package->hasBladeComponentNamespace('App\\View\\Components', 'acme');

        $this->assertSame($this->package, $result, 'Should support fluent chaining');
        $this->assertSame(
            ['App\\View\\Components' => 'acme'],
            $this->package->bladeComponentNamespaces,
        );
    }

    #[Test]
    public function it_can_register_a_single_blade_component_alias(): void
    {
        $result = $this->package->hasBladeComponentAlias('acme-alert', 'App\\View\\Components\\Alert');

        $this->assertSame($this->package, $result, 'Should support fluent chaining');
        $this->assertSame(
            ['acme-alert' => 'App\\View\\Components\\Alert'],
            $this->package->bladeComponentAliases,
        );
    }

    #[Test]
    public function it_can_register_multiple_blade_component_aliases(): void
    {
        $this->package->hasBladeComponentAliases([
            'acme-alert' => 'App\\View\\Components\\Alert',
            'acme-badge' => 'App\\View\\Components\\Badge',
        ]);

        $this->assertSame([
            'acme-alert' => 'App\\View\\Components\\Alert',
            'acme-badge' => 'App\\View\\Components\\Badge',
        ], $this->package->bladeComponentAliases);
    }

    #[Test]
    public function aliases_and_namespaces_are_stored_independently_of_view_components(): void
    {
        $this->package
            ->hasViewComponent('prefix', 'Alert')
            ->hasBladeComponentNamespace('App\\View\\Components', 'acme')
            ->hasBladeComponentAlias('acme-alert', 'App\\View\\Components\\Alert');

        $this->assertCount(1, $this->package->viewComponents);
        $this->assertCount(1, $this->package->bladeComponentNamespaces);
        $this->assertCount(1, $this->package->bladeComponentAliases);
    }
}
