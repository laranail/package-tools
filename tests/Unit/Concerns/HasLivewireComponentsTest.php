<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Tests\Unit\Concerns;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\PackageTools\Package;

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
}
