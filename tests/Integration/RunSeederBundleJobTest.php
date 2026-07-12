<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Integration;

use Illuminate\Database\Seeder;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\Package\Tools\Enums\SeederExecutionMode;
use Simtabi\Laranail\Package\Tools\Enums\SeederRunStatus;
use Simtabi\Laranail\Package\Tools\Jobs\RunSeederBundleJob;
use Simtabi\Laranail\Package\Tools\Providers\PackageToolsServiceProvider;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederAutorun;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederExecutor;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederRegistry;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederRunTracker;

final class RunSeederBundleJobTest extends TestCase
{
    protected function setUp(): void
    {
        JobLedgerFixture::reset();

        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [PackageToolsServiceProvider::class];
    }

    private function handle(RunSeederBundleJob $job): void
    {
        $job->handle(
            $this->app->make(SeederRegistry::class),
            $this->app->make(SeederExecutor::class),
            $this->app->make(SeederAutorun::class),
        );
    }

    public function test_the_job_resolves_its_bundle_by_key_and_executes_it(): void
    {
        $this->app->make(SeederRegistry::class)
            ->register('t/job', [JobFixtureSeeder::class]);

        $this->handle(new RunSeederBundleJob('t/job'));

        $this->assertSame([JobFixtureSeeder::class], JobLedgerFixture::$ran);
    }

    public function test_a_missing_bundle_key_warns_and_no_ops(): void
    {
        $this->handle(new RunSeederBundleJob('t/vanished'));

        $this->assertSame([], JobLedgerFixture::$ran);
    }

    public function test_the_run_is_marked_in_the_ledger(): void
    {
        $this->app->make(SeederRegistry::class)
            ->register('t/job', [JobFixtureSeeder::class]);

        $this->handle(new RunSeederBundleJob('t/job'));

        $this->assertTrue($this->app->make(SeederAutorun::class)->hasExecuted('t/job'));
    }

    public function test_the_tracker_transitions_to_completed(): void
    {
        $this->app->make(SeederRegistry::class)
            ->register('t/job', [JobFixtureSeeder::class]);

        $this->handle(new RunSeederBundleJob('t/job'));

        $state = $this->app->make(SeederRunTracker::class)->get('t/job');

        $this->assertNotNull($state);
        $this->assertSame(SeederRunStatus::Completed, $state['status']);
        $this->assertSame(1, $state['processed']);
    }

    public function test_queue_settings_come_from_config(): void
    {
        config()->set('package-tools.seeders.queue.name', 'seeding');
        config()->set('package-tools.seeders.queue.connection', 'redis');
        config()->set('package-tools.seeders.queue.tries', 3);
        config()->set('package-tools.seeders.queue.timeout', 120);

        $job = new RunSeederBundleJob('t/job');

        $this->assertSame('seeding', $job->queue);
        $this->assertSame('redis', $job->connection);
        $this->assertSame(3, $job->tries);
        $this->assertSame(120, $job->timeout);
        $this->assertSame(SeederExecutionMode::Queued, $job->mode);
    }
}

final class JobLedgerFixture
{
    /** @var list<class-string> */
    public static array $ran = [];

    public static function reset(): void
    {
        self::$ran = [];
    }
}

final class JobFixtureSeeder extends Seeder
{
    public function run(): void
    {
        JobLedgerFixture::$ran[] = self::class;
    }
}
