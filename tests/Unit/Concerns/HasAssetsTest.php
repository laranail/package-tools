<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Concerns;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\Package\Tools\Package;

/**
 * Tests for HasAssets concern
 */
class HasAssetsTest extends TestCase
{
    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();
        $this->package = new Package;
        $this->package->setName('test-vendor/test-package');
    }

    #[Test]
    public function it_can_register_assets(): void
    {
        $result = $this->package->hasAssets();

        $this->assertSame($this->package, $result, 'Should support fluent chaining');
        $this->assertTrue($this->package->hasAssets);
    }

    #[Test]
    public function it_uses_default_assets_directory(): void
    {
        $this->package->setPathFrom('/var/www/package')->hasAssets();

        $this->assertTrue($this->package->hasAssets);
        $this->assertSame('resources/assets', Package::ASSETS_DIR);
    }
}
