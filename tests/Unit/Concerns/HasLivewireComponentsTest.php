<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Concerns;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\Package\Tools\Package;

/**
 * Tests for HasLivewireComponents concern
 */
class HasLivewireComponentsTest extends TestCase
{
    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();
        $this->package = new Package;
        $this->package->setName('test-vendor/test-package');
    }

    #[Test]
    public function it_can_register_livewire_components_with_array(): void
    {
        $components = [
            'icon-browser' => 'App\\Components\\IconBrowser',
            'file-upload' => 'App\\Components\\FileUpload',
        ];

        $result = $this->package->hasLivewireComponents($components);

        $this->assertSame($this->package, $result, 'Should support fluent chaining');
        $this->assertArrayHasKey('icon-browser', $this->package->livewireComponents);
        $this->assertArrayHasKey('file-upload', $this->package->livewireComponents);
    }

    #[Test]
    public function it_can_register_single_livewire_component(): void
    {
        $result = $this->package->hasLivewireComponent('test-component', 'App\\Components\\TestComponent');

        $this->assertSame($this->package, $result, 'Should support fluent chaining');
        $this->assertArrayHasKey('test-component', $this->package->livewireComponents);
        $this->assertSame('App\\Components\\TestComponent', $this->package->livewireComponents['test-component']);
    }

    #[Test]
    public function it_can_register_livewire_components_from_a_closure(): void
    {
        $this->package->hasLivewireComponents(static fn (): array => [
            'icon-browser' => 'App\\Components\\IconBrowser',
        ]);

        $this->assertSame(
            ['icon-browser' => 'App\\Components\\IconBrowser'],
            $this->package->livewireComponents,
        );
    }

    #[Test]
    public function it_has_no_gate_by_default(): void
    {
        $this->package->hasLivewireComponents(['a' => 'App\\A']);

        $this->assertNull($this->package->livewireGate);
    }

    #[Test]
    public function when_config_stores_a_truthy_package_level_gate(): void
    {
        $this->package->hasLivewireComponents(['a' => 'App\\A'], 'test-package.livewire.enabled');

        $this->assertNotNull($this->package->livewireGate);
        $this->assertSame([
            'key' => 'test-package.livewire.enabled',
            'default' => true,
            'mode' => 'truthy',
        ], $this->package->livewireGate->toArray());
    }

    #[Test]
    public function when_config_keeps_the_given_default(): void
    {
        $this->package->hasLivewireComponents(['a' => 'App\\A'], 'test-package.livewire.enabled', false);

        $this->assertFalse($this->package->livewireGate->toArray()['default']);
    }

    #[Test]
    public function a_later_gate_replaces_an_earlier_one(): void
    {
        $this->package->hasLivewireComponents(['a' => 'App\\A'], 'test-package.first');
        $this->package->hasLivewireComponents(['b' => 'App\\B'], 'test-package.second');

        $this->assertSame('test-package.second', $this->package->livewireGate->key());
    }

    #[Test]
    public function components_prefix_with_the_namespace_by_default(): void
    {
        $this->assertTrue($this->package->livewirePrefixComponents);
    }

    #[Test]
    public function without_livewire_namespace_prefix_disables_prefixing(): void
    {
        $result = $this->package->withoutLivewireNamespacePrefix();

        $this->assertSame($this->package, $result, 'Should support fluent chaining');
        $this->assertFalse($this->package->livewirePrefixComponents);
    }
}
