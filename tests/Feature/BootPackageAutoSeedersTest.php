<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature;

use Illuminate\Database\Seeder;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\Package\Tools\Providers\PackageToolsServiceProvider;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederManager;
use Simtabi\Laranail\Package\Tools\Support\Definitions\AutoSeederDefinition;
use Simtabi\Laranail\Package\Tools\Tests\Fixtures\AutoSeeders\DiscoveredAlphaSeeder;
use Simtabi\Laranail\Package\Tools\Tests\Fixtures\AutoSeeders\DiscoveredBetaSeeder;
use Simtabi\Laranail\Package\Tools\Tests\Fixtures\AutoSeeders\DiscoveredIgnoredSeeder;

/**
 * hasPackageSeeders() must REGISTER bundles with the shared SeederManager
 * at boot (gates evaluated then) and EXECUTE nothing until the host's
 * db:seed path resolves a ROOT seeder through the container — the
 * SeederResolverHook's contract. (The discoverer requires non-autoloaded
 * fixture files itself since 3.0 — no require_once shims here.)
 */
final class BootPackageAutoSeedersTest extends TestCase
{
    protected function setUp(): void
    {
        SeederRunLedger::reset();
        SeederFixtureAlpha::$ran = false;

        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            PackageToolsServiceProvider::class,
            AutoSeederPackageProviderA::class,
            AutoSeederHighPriorityProvider::class,
            AutoSeederLowPriorityProvider::class,
            AutoSeederDiscoveryProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // The default root (Database\Seeders\DatabaseSeeder) doesn't exist
        // in the Testbench skeleton; register a fixture root instead.
        $app['config']->set('package-tools.seeders.root_seeders', [TestRootSeeder::class]);
    }

    protected function disableSeedGate(Application $app): void
    {
        $app['config']->set('test.seed_on', false);
    }

    public function test_gated_bundle_registers_when_the_config_gate_is_on(): void
    {
        // 'test.seed_on' unset: the declared default (true) applies
        $bundle = $this->manager()->registry()->get('t/pkg');

        $this->assertNotNull($bundle);
        $this->assertSame([SeederFixtureAlpha::class], $bundle->seeders());
    }

    #[DefineEnvironment('disableSeedGate')]
    public function test_gated_bundle_is_absent_when_the_config_gate_is_off(): void
    {
        $this->assertNull($this->manager()->registry()->get('t/pkg'));
    }

    public function test_seeders_never_execute_at_boot(): void
    {
        $this->assertFalse(SeederFixtureAlpha::$ran, 'boot must only register, never run seeders');
        $this->assertSame([], SeederRunLedger::$order);
    }

    public function test_resolving_the_root_seeder_triggers_execution(): void
    {
        $this->assertFalse(SeederFixtureAlpha::$ran);

        // db:seed resolves the root seeder through the container; ONLY the
        // exact root FQCNs fire the SeederResolverHook listener.
        $this->app->make(TestRootSeeder::class);

        $this->assertTrue(SeederFixtureAlpha::$ran);
        $this->assertContains(SeederFixtureHigh::class, SeederRunLedger::$order);
        $this->assertContains(SeederFixtureLow::class, SeederRunLedger::$order);
    }

    public function test_resolving_an_arbitrary_seeder_does_not_trigger_execution(): void
    {
        // Regression guard for the 2.x footgun: any Seeder resolution ran
        // every package bundle (db:seed --class=X, web requests, the
        // executor's own make() calls).
        $this->app->make(SeederFixtureAlpha::class);

        $this->assertSame([], SeederRunLedger::$order);
    }

    public function test_discovery_mode_resolves_classes_and_honors_the_ignore_list(): void
    {
        $bundle = $this->manager()->registry()->get('t/discovered');

        $this->assertNotNull($bundle);
        $this->assertContains(DiscoveredAlphaSeeder::class, $bundle->seeders());
        $this->assertContains(DiscoveredBetaSeeder::class, $bundle->seeders());
        $this->assertNotContains(DiscoveredIgnoredSeeder::class, $bundle->seeders());
    }

    public function test_cross_bundle_execution_order_follows_priority_lowest_first(): void
    {
        $this->app->make(TestRootSeeder::class);

        $lowIndex = array_search(SeederFixtureLow::class, SeederRunLedger::$order, true);
        $highIndex = array_search(SeederFixtureHigh::class, SeederRunLedger::$order, true);

        $this->assertNotFalse($lowIndex);
        $this->assertNotFalse($highIndex);
        $this->assertLessThan($highIndex, $lowIndex, 'priority 0 must execute before priority 10');
    }

    private function manager(): SeederManager
    {
        return $this->app->make(SeederManager::class);
    }
}

/**
 * process-global record of seeder executions, shared with the discovery
 * fixtures; reset per test.
 */
final class SeederRunLedger
{
    /** @var list<class-string> */
    public static array $order = [];

    public static function record(string $class): void
    {
        self::$order[] = $class;
    }

    public static function reset(): void
    {
        self::$order = [];
    }
}

class TestRootSeeder extends Seeder
{
    public function run(): void {}
}

final class SeederFixtureAlpha extends Seeder
{
    public static bool $ran = false;

    public function run(): void
    {
        self::$ran = true;
        SeederRunLedger::record(self::class);
    }
}

final class SeederFixtureHigh extends Seeder
{
    public function run(): void
    {
        SeederRunLedger::record(self::class);
    }
}

final class SeederFixtureLow extends Seeder
{
    public function run(): void
    {
        SeederRunLedger::record(self::class);
    }
}

final class AutoSeederPackageProviderA extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->setName('test/auto-seeders-a');
        $package->basePath = sys_get_temp_dir();

        $package->hasPackageSeeders(
            AutoSeederDefinition::make('t/pkg')
                ->seeders([SeederFixtureAlpha::class])
                ->whenConfig('test.seed_on'),
        );
    }
}

final class AutoSeederHighPriorityProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->setName('test/auto-seeders-high');
        $package->basePath = sys_get_temp_dir();

        $package->hasPackageSeeders(
            AutoSeederDefinition::make('t/high')
                ->seeders([SeederFixtureHigh::class])
                ->priority(10),
        );
    }
}

final class AutoSeederLowPriorityProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->setName('test/auto-seeders-low');
        $package->basePath = sys_get_temp_dir();

        $package->hasPackageSeeders(
            AutoSeederDefinition::make('t/low')
                ->seeders([SeederFixtureLow::class])
                ->priority(0),
        );
    }
}

final class AutoSeederDiscoveryProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->setName('test/auto-seeders-discovery');
        $package->basePath = sys_get_temp_dir();

        $package->hasPackageSeeders(
            AutoSeederDefinition::make('t/discovered')
                ->discoverIn(dirname(__DIR__) . '/fixtures/auto-seeders')
                ->ignoreSeeders([DiscoveredIgnoredSeeder::class]),
        );
    }
}
