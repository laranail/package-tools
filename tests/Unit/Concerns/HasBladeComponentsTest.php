<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Tests\Unit\Concerns;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\PackageTools\Package;

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
}
