<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Concerns;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\Package\Tools\Package;

/**
 * Tests for HasConfigs concern
 */
class HasConfigsTest extends TestCase
{
    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();
        $this->package = new Package;
        $this->package->setName('test-vendor/test-package');
    }

    #[Test]
    public function it_can_register_single_config_file(): void
    {
        $result = $this->package->hasConfigFile();

        $this->assertSame($this->package, $result, 'Should support fluent chaining');
        $this->assertNotEmpty($this->package->configFileNames);
        $this->assertContains('test-package', $this->package->configFileNames);
    }

    #[Test]
    public function it_can_register_config_with_custom_name(): void
    {
        $this->package->hasConfigFile('custom-config');

        $this->assertContains('custom-config', $this->package->configFileNames);
    }

    #[Test]
    public function it_can_register_multiple_config_files(): void
    {
        $this->package
            ->hasConfigFile('config1')
            ->hasConfigFile('config2')
            ->hasConfigFile('config3');

        $this->assertCount(3, $this->package->configFileNames);
        $this->assertContains('config1', $this->package->configFileNames);
        $this->assertContains('config2', $this->package->configFileNames);
        $this->assertContains('config3', $this->package->configFileNames);
    }

    #[Test]
    public function it_uses_package_name_as_default_config_name(): void
    {
        $this->package->setName('test-vendor/my-package')->hasConfigFile();

        $this->assertContains('my-package', $this->package->configFileNames);
    }

    #[Test]
    public function it_prevents_duplicate_config_files(): void
    {
        $this->package
            ->hasConfigFile('config1')
            ->hasConfigFile('config1');

        // Should only have one instance
        $this->assertCount(1, $this->package->configFileNames);
    }

    #[Test]
    public function it_stores_config_files_as_array(): void
    {
        $this->package->hasConfigFile('test');

        $this->assertIsArray($this->package->configFileNames);
    }
}
