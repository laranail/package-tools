<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Package\Tools\Concerns\Package\HasFactoriesAndSeeders;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Support\Definitions\AutoSeederDefinition;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

/**
 * HasFactoriesAndSeedersTest - Test factory & seeder management
 *
 * The seeder half asserts against the definition pipeline: since 3.0,
 * loadSeedersFrom()/registerSeeder() create real AutoSeederDefinitions
 * (they were silent no-ops before).
 */
class HasFactoriesAndSeedersTest extends TestCase
{
    use HasFactoriesAndSeeders;

    protected string $basePath = '/var/www/test-package';

    private function makePackage(): Package
    {
        $package = new Package;
        $package->name('acme/blog');
        $package->basePath = '/var/www/test-package';

        return $package;
    }

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
    public function it_creates_a_discovery_definition_per_seeder_path(): void
    {
        $package = $this->makePackage();
        $package->loadSeedersFrom('database/seeders');
        $package->loadSeedersFrom('tests/seeders');

        $definitions = $package->getPackageSeederDefinitions();

        $this->assertCount(2, $definitions);

        foreach ($definitions as $definition) {
            $this->assertInstanceOf(AutoSeederDefinition::class, $definition);
            $this->assertNotNull($definition->toArray()['discovery_path']);
        }

        // Distinct paths must yield distinct keys — no registry clobbering.
        $keys = array_map(static fn (AutoSeederDefinition $d): string => $d->key(), $definitions);
        $this->assertSame($keys, array_unique($keys));
    }

    #[Test]
    public function it_resolves_seeder_paths_relative_to_the_package_root(): void
    {
        $package = $this->makePackage();
        $package->loadSeedersFrom('database/seeders');

        [$definition] = $package->getPackageSeederDefinitions();

        $this->assertStringContainsString('test-package', (string) $definition->toArray()['discovery_path']);
    }

    #[Test]
    public function registered_seeders_append_to_one_shared_definition_in_call_order(): void
    {
        $package = $this->makePackage();
        $package->registerSeeder('Database\Seeders\BlogSeeder');
        $package->registerSeeder('Database\Seeders\UserSeeder');

        $definitions = $package->getPackageSeederDefinitions();

        $this->assertCount(1, $definitions);
        $this->assertSame(
            ['Database\Seeders\BlogSeeder', 'Database\Seeders\UserSeeder'],
            $definitions[0]->toArray()['seeders'],
        );
        // name('acme/blog') splits the vendor prefix off; the package part
        // keys the shared default definition.
        $this->assertSame('blog', $definitions[0]->key());
    }

    #[Test]
    public function duplicate_seeder_registrations_are_ignored(): void
    {
        $package = $this->makePackage();
        $package->registerSeeder('Database\Seeders\BlogSeeder');
        $package->registerSeeder('Database\Seeders\BlogSeeder');

        [$definition] = $package->getPackageSeederDefinitions();

        $this->assertCount(1, $definition->toArray()['seeders']);
    }

    #[Test]
    public function it_returns_empty_arrays_when_nothing_registered(): void
    {
        $this->assertEmpty($this->getFactoryPaths());
        $this->assertEmpty($this->makePackage()->getPackageSeederDefinitions());
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
        $package = $this->makePackage();
        $result = $package->loadSeedersFrom('database/seeders')
            ->registerSeeder('BlogSeeder');

        $this->assertSame($package, $result);
        $this->assertCount(2, $package->getPackageSeederDefinitions());
    }

    #[Test]
    public function autorun_seeders_flag_is_stored(): void
    {
        $package = $this->makePackage();

        $this->assertSame($package, $package->autorunSeeders());
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
