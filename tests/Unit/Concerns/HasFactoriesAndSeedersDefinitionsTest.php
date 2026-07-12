<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Concerns;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Support\Definitions\AutoSeederDefinition;

/**
 * the db:seed-time seeder surface of HasFactoriesAndSeeders: the
 * hasPackageSeeders() shorthand and definition passthrough, plus the
 * discoverPackageSeedersIn() sugar. path/class storage is covered by the
 * original HasFactoriesAndSeedersTest.
 */
class HasFactoriesAndSeedersDefinitionsTest extends TestCase
{
    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();
        $this->package = new Package;
        $this->package->setName('test-vendor/test-package');
    }

    #[Test]
    public function the_shorthand_wraps_key_and_seeders_into_a_definition(): void
    {
        $result = $this->package->hasPackageSeeders('test-package', ['App\\Seeders\\A', 'App\\Seeders\\B']);

        $this->assertSame($this->package, $result, 'Should support fluent chaining');

        $definitions = $this->package->getPackageSeederDefinitions();

        $this->assertCount(1, $definitions);
        $this->assertSame('test-package', $definitions[0]->key());
        $this->assertSame(
            ['App\\Seeders\\A', 'App\\Seeders\\B'],
            $definitions[0]->toArray()['seeders'],
        );
    }

    #[Test]
    public function a_prebuilt_definition_is_stored_as_is(): void
    {
        $definition = AutoSeederDefinition::make('test-package')
            ->seeders(['App\\Seeders\\A'])
            ->priority(3);

        $this->package->hasPackageSeeders($definition);

        $this->assertSame([$definition], $this->package->getPackageSeederDefinitions());
    }

    #[Test]
    public function definitions_accumulate_in_registration_order(): void
    {
        $this->package->hasPackageSeeders('first', ['App\\Seeders\\A']);
        $this->package->hasPackageSeeders('second', ['App\\Seeders\\B']);

        $definitions = $this->package->getPackageSeederDefinitions();

        $this->assertSame('first', $definitions[0]->key());
        $this->assertSame('second', $definitions[1]->key());
    }

    #[Test]
    public function discover_package_seeders_in_creates_a_discovery_definition(): void
    {
        $this->package->discoverPackageSeedersIn('/pkg/database/seeders', 'Acme\\Blog');

        $definition = $this->package->getPackageSeederDefinitions()[0];

        // Keys include the path hash so same-namespace calls with different
        // paths never clobber each other in the shared registry.
        $this->assertSame('Acme\\Blog:' . md5('/pkg/database/seeders'), $definition->key());
        $this->assertSame('Acme\\Blog', $definition->namespace());
        $this->assertSame([], $definition->toArray()['seeders']);
        $this->assertSame('/pkg/database/seeders', $definition->toArray()['discovery_path']);
    }

    #[Test]
    public function discover_package_seeders_in_derives_a_key_when_no_namespace_is_given(): void
    {
        $this->package->discoverPackageSeedersIn('/pkg/database/seeders');

        $definition = $this->package->getPackageSeederDefinitions()[0];

        $this->assertSame('discovered:' . md5('/pkg/database/seeders'), $definition->key());
        $this->assertNull($definition->namespace());
    }

    #[Test]
    public function it_has_no_definitions_by_default(): void
    {
        $this->assertSame([], $this->package->getPackageSeederDefinitions());
    }
}
