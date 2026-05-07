<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Tests\Unit\Concerns;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\PackageTools\Package;

/**
 * Tests for HasMigrations concern
 */
class HasMigrationsTest extends TestCase
{
    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();
        $this->package = new Package;
        $this->package->setName('test-vendor/test-package');
    }

    #[Test]
    public function it_can_register_migrations(): void
    {
        $result = $this->package->hasMigrations();

        $this->assertSame($this->package, $result, 'Should support fluent chaining');
        $this->assertTrue($this->package->hasMigrations);
    }

    #[Test]
    public function it_uses_default_migrations_directory(): void
    {
        $this->package->setPathFrom('/var/www/package')->hasMigrations();

        $this->assertTrue($this->package->hasMigrations);
        // Directory constant check
        $this->assertSame('database/migrations', Package::MIGRATIONS_DIR);
    }

    #[Test]
    public function it_can_register_migrations_without_running_them(): void
    {
        $this->package->hasMigrations();

        // Just registers, doesn't run
        $this->assertTrue($this->package->hasMigrations);
    }
}
