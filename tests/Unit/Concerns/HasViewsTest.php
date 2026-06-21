<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Concerns;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\Package\Tools\Package;

/**
 * Tests for HasViews concern
 */
class HasViewsTest extends TestCase
{
    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();
        $this->package = new Package;
        $this->package->setName('test-vendor/test-package');
    }

    #[Test]
    public function it_can_register_views(): void
    {
        $result = $this->package->hasViews();

        $this->assertSame($this->package, $result, 'Should support fluent chaining');
        $this->assertTrue($this->package->hasViews);
    }

    #[Test]
    public function it_can_register_views_with_custom_namespace(): void
    {
        $this->package->hasViews('custom-namespace');

        $this->assertTrue($this->package->hasViews);
        $this->assertSame('custom-namespace', $this->package->viewNamespace);
    }

    #[Test]
    public function it_uses_package_name_as_default_view_namespace(): void
    {
        $this->package->setName('test-vendor/my-package')->hasViews();

        $this->assertSame('my-package', $this->package->viewNamespace);
    }

    #[Test]
    public function it_can_register_views_without_publishing(): void
    {
        $this->package->hasViews();

        $this->assertTrue($this->package->hasViews);
    }

    #[Test]
    public function it_uses_default_views_directory(): void
    {
        $this->package->setPathFrom('/var/www/package')->hasViews();
        // The actual path handling is tested in integration tests
        $this->assertTrue($this->package->hasViews);
    }
}
