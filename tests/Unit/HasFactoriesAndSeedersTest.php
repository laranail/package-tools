<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\PackageTools\Concerns\Package\HasFactoriesAndSeeders;
use Simtabi\Laranail\PackageTools\Tests\TestCase;

/**
 * HasFactoriesAndSeedersTest - Test factory & seeder management
 *
 * Covers Phase 6 factories & seeders features
 */
class HasFactoriesAndSeedersTest extends TestCase
{
    use HasFactoriesAndSeeders;

    protected string $basePath = '/var/www/test-package';

    #[Test]
    public function it_loads_factories_from_path(): void
    {
        $this->loadFactoriesFrom('database/factories');

        $paths = $this->getFactoryPaths();

        $this->assertCount(1, $paths);
        $this->assertContains('database/factories', $paths);
    }

    #[Test]
    public function it_loads_multiple_factory_paths(): void
    {
        $this->loadFactoriesFrom('database/factories');
        $this->loadFactoriesFrom('tests/factories');

        $paths = $this->getFactoryPaths();

        $this->assertCount(2, $paths);
    }

    #[Test]
    public function it_loads_seeders_from_path(): void
    {
        $this->loadSeedersFrom('database/seeders');

        $paths = $this->getSeederPaths();

        $this->assertCount(1, $paths);
        $this->assertContains('database/seeders', $paths);
    }

    #[Test]
    public function it_loads_multiple_seeder_paths(): void
    {
        $this->loadSeedersFrom('database/seeders');
        $this->loadSeedersFrom('tests/seeders');

        $paths = $this->getSeederPaths();

        $this->assertCount(2, $paths);
    }

    #[Test]
    public function it_registers_seeder_class(): void
    {
        $this->registerSeeder('Database\Seeders\BlogSeeder');

        $seeders = $this->getRegisteredSeeders();

        $this->assertCount(1, $seeders);
        $this->assertContains('Database\Seeders\BlogSeeder', $seeders);
    }

    #[Test]
    public function it_registers_multiple_seeder_classes(): void
    {
        $this->registerSeeder('BlogSeeder');
        $this->registerSeeder('UserSeeder');

        $seeders = $this->getRegisteredSeeders();

        $this->assertCount(2, $seeders);
    }

    #[Test]
    public function it_returns_empty_arrays_when_nothing_registered(): void
    {
        $this->assertEmpty($this->getFactoryPaths());
        $this->assertEmpty($this->getSeederPaths());
        $this->assertEmpty($this->getRegisteredSeeders());
    }

    #[Test]
    public function it_chains_factory_registrations(): void
    {
        $result = $this->loadFactoriesFrom('database/factories')
            ->loadFactoriesFrom('tests/factories');

        $this->assertInstanceOf(static::class, $result);
        $this->assertCount(2, $this->getFactoryPaths());
    }

    #[Test]
    public function it_chains_seeder_registrations(): void
    {
        $result = $this->loadSeedersFrom('database/seeders')
            ->registerSeeder('BlogSeeder');

        $this->assertInstanceOf(static::class, $result);
    }

    #[Test]
    public function it_supports_fluent_api(): void
    {
        $this->loadFactoriesFrom('database/factories')
            ->loadSeedersFrom('database/seeders')
            ->registerSeeder('BlogSeeder');

        $this->assertCount(1, $this->getFactoryPaths());
        $this->assertCount(1, $this->getSeederPaths());
        $this->assertCount(1, $this->getRegisteredSeeders());
    }

    #[Test]
    public function it_maintains_path_registration_order(): void
    {
        $this->loadFactoriesFrom('first');
        $this->loadFactoriesFrom('second');
        $this->loadFactoriesFrom('third');

        $paths = $this->getFactoryPaths();

        $this->assertEquals('first', $paths[0]);
        $this->assertEquals('second', $paths[1]);
        $this->assertEquals('third', $paths[2]);
    }

    #[Test]
    public function it_allows_duplicate_paths(): void
    {
        $this->loadFactoriesFrom('database/factories');
        $this->loadFactoriesFrom('database/factories');

        $paths = $this->getFactoryPaths();

        $this->assertCount(2, $paths);
    }

    #[Test]
    public function it_handles_relative_paths(): void
    {
        $this->loadFactoriesFrom('database/factories');
        $this->loadFactoriesFrom('../../shared/factories');

        $paths = $this->getFactoryPaths();

        $this->assertCount(2, $paths);
    }
}
