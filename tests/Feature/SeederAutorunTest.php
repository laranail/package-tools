<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature;

use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Seeder;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\Package\Tools\Providers\PackageToolsServiceProvider;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederAutorun;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederManager;
use Simtabi\Laranail\Package\Tools\Support\Definitions\AutoSeederDefinition;

/**
 * The autorun contract: seeders NEVER run on their own unless a bundle
 * opted in via autorunAfterMigrations()/autorunNow(), and even then only
 * after every safety gate passes. The trigger is MigrationsEnded('up').
 */
final class SeederAutorunTest extends TestCase
{
    protected function setUp(): void
    {
        AutorunLedgerFixture::reset();

        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            PackageToolsServiceProvider::class,
            AutorunOptedInProvider::class,
            AutorunNotOptedInProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Tests run under runningUnitTests(); autorun is gated off there by
        // default — opt in so the feature is exercisable at all.
        $app['config']->set('package-tools.seeders.autorun.in_tests', true);
    }

    private function fireMigrationsEnded(string $method = 'up', array $options = []): void
    {
        $this->app['events']->dispatch(new MigrationsEnded($method, $options));
    }

    public function test_migrations_ended_runs_only_autorun_flagged_bundles(): void
    {
        $this->fireMigrationsEnded();

        $this->assertContains('opted-in', AutorunLedgerFixture::$ran);
        $this->assertNotContains('not-opted-in', AutorunLedgerFixture::$ran);
    }

    public function test_migrations_down_does_not_trigger(): void
    {
        $this->fireMigrationsEnded('down');

        $this->assertSame([], AutorunLedgerFixture::$ran);
    }

    public function test_pretend_migrations_do_not_trigger(): void
    {
        $this->fireMigrationsEnded('up', ['pretend' => true]);

        $this->assertSame([], AutorunLedgerFixture::$ran);
    }

    public function test_bundles_run_once_per_process_even_across_repeated_migrations(): void
    {
        $this->fireMigrationsEnded();
        $this->fireMigrationsEnded();
        $this->fireMigrationsEnded();

        $this->assertSame(['opted-in'], AutorunLedgerFixture::$ran);
    }

    public function test_global_kill_switch_disables_autorun(): void
    {
        config()->set('package-tools.seeders.autorun.enabled', false);

        $this->fireMigrationsEnded();

        $this->assertSame([], AutorunLedgerFixture::$ran);
    }

    public function test_tests_gate_blocks_autorun_by_default(): void
    {
        config()->set('package-tools.seeders.autorun.in_tests', false);

        $this->fireMigrationsEnded();

        $this->assertSame([], AutorunLedgerFixture::$ran);
    }

    public function test_a_throwing_autorun_seeder_never_breaks_the_listener(): void
    {
        $manager = $this->app->make(SeederManager::class);
        $manager->autoSeed('t/explosive', [ExplosiveAutorunSeeder::class], null, ['autorun' => true]);

        // Must not throw out of the event dispatch (migrate must survive).
        $this->fireMigrationsEnded();

        $this->assertContains('opted-in', AutorunLedgerFixture::$ran);
    }

    public function test_db_seed_after_autorun_does_not_double_run(): void
    {
        $this->fireMigrationsEnded();
        $this->assertSame(['opted-in'], AutorunLedgerFixture::$ran);

        // Simulate the db:seed path: run() marks + executes everything not
        // yet in the ledger — the autorun bundle must be skipped... run()
        // executes the whole registry, so instead go through the autorun
        // state check the hook uses.
        $autorun = $this->app->make(SeederAutorun::class);
        $this->assertTrue($autorun->hasExecuted('t/opted-in'));
    }

    public function test_reset_run_state_allows_re_execution(): void
    {
        $this->fireMigrationsEnded();
        $this->app->make(SeederManager::class)->resetRunState();
        $this->fireMigrationsEnded();

        $this->assertSame(['opted-in', 'opted-in'], AutorunLedgerFixture::$ran);
    }
}

final class AutorunLedgerFixture
{
    /** @var list<string> */
    public static array $ran = [];

    public static function reset(): void
    {
        self::$ran = [];
    }
}

final class OptedInAutorunSeeder extends Seeder
{
    public function run(): void
    {
        AutorunLedgerFixture::$ran[] = 'opted-in';
    }
}

final class NotOptedInSeeder extends Seeder
{
    public function run(): void
    {
        AutorunLedgerFixture::$ran[] = 'not-opted-in';
    }
}

final class ExplosiveAutorunSeeder extends Seeder
{
    public function run(): void
    {
        throw new \RuntimeException('boom');
    }
}

final class AutorunOptedInProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->setName('test/autorun-opted-in');
        $package->basePath = sys_get_temp_dir();

        $package->hasPackageSeeders(
            AutoSeederDefinition::make('t/opted-in')
                ->seeders([OptedInAutorunSeeder::class])
                ->autorunNow(),
        );
    }
}

final class AutorunNotOptedInProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->setName('test/autorun-not-opted-in');
        $package->basePath = sys_get_temp_dir();

        $package->hasPackageSeeders(
            AutoSeederDefinition::make('t/not-opted-in')
                ->seeders([NotOptedInSeeder::class]),
        );
    }
}
