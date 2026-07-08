<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Event;
use Orchestra\Testbench\TestCase;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Enums\SeederExecutionMode;
use Simtabi\Laranail\Package\Tools\Events\PackageSeedingCompleted;
use Simtabi\Laranail\Package\Tools\Events\PackageSeedingFailed;
use Simtabi\Laranail\Package\Tools\Events\PackageSeedingStarted;
use Simtabi\Laranail\Package\Tools\Providers\PackageToolsServiceProvider;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederExecutor;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederRegistry;

/**
 * PackageSeeding* events: the host's "notify when done" hook. Fired for
 * EVERY mode by default; suppressed per bundle (notifiesOnCompletion
 * false) or globally (package-tools.seeders.events.enabled).
 */
final class SeederEventsTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [PackageToolsServiceProvider::class];
    }

    public function test_started_and_completed_fire_for_inline_runs_by_default(): void
    {
        Event::fake([PackageSeedingStarted::class, PackageSeedingCompleted::class]);

        $registry = (new SeederRegistry)
            ->register('acme/blog', [EventFixtureSeeder::class], 'Acme\\Blog');

        (new SeederExecutor($this->app))->run($registry);

        Event::assertDispatched(PackageSeedingStarted::class, fn (PackageSeedingStarted $event): bool => $event->bundleKey === 'acme/blog'
            && $event->packageName === 'Acme\\Blog'
            && $event->seederCount === 1
            && $event->mode === SeederExecutionMode::Inline);
        Event::assertDispatched(PackageSeedingCompleted::class, fn (PackageSeedingCompleted $event): bool => $event->stats->success === 1
            && $event->stats->failed === 0
            && $event->durationMs >= 0);
    }

    public function test_failed_fires_per_failing_seeder_and_isolation_holds(): void
    {
        Event::fake([PackageSeedingFailed::class, PackageSeedingCompleted::class]);

        $registry = (new SeederRegistry)
            ->register('acme/fragile', [ExplodingEventSeeder::class, EventFixtureSeeder::class]);

        $stats = (new SeederExecutor($this->app))->run($registry);

        Event::assertDispatched(PackageSeedingFailed::class, fn (PackageSeedingFailed $event): bool => $event->seederClass === ExplodingEventSeeder::class
            && $event->exceptionClass === RuntimeException::class
            && $event->message === 'boom');
        // The bundle still completed (with stats reflecting the failure).
        Event::assertDispatched(PackageSeedingCompleted::class);
        $this->assertSame(1, $stats->failed);
        $this->assertSame(1, $stats->success);
    }

    public function test_per_bundle_opt_out_suppresses_the_events(): void
    {
        Event::fake([PackageSeedingStarted::class, PackageSeedingCompleted::class]);

        $registry = (new SeederRegistry)
            ->register('acme/quiet', [EventFixtureSeeder::class], null, ['notify' => false]);

        (new SeederExecutor($this->app))->run($registry);

        Event::assertNotDispatched(PackageSeedingStarted::class);
        Event::assertNotDispatched(PackageSeedingCompleted::class);
    }

    public function test_the_global_kill_switch_suppresses_the_events(): void
    {
        config()->set('package-tools.seeders.events.enabled', false);
        Event::fake([PackageSeedingStarted::class, PackageSeedingCompleted::class]);

        $registry = (new SeederRegistry)
            ->register('acme/blog', [EventFixtureSeeder::class]);

        (new SeederExecutor($this->app))->run($registry);

        Event::assertNotDispatched(PackageSeedingStarted::class);
        Event::assertNotDispatched(PackageSeedingCompleted::class);
    }

    public function test_the_mode_is_carried_through(): void
    {
        Event::fake([PackageSeedingCompleted::class]);

        $registry = (new SeederRegistry)
            ->register('acme/blog', [EventFixtureSeeder::class]);

        (new SeederExecutor($this->app))->run($registry, SeederExecutionMode::Scheduled);

        Event::assertDispatched(
            PackageSeedingCompleted::class,
            static fn (PackageSeedingCompleted $event): bool => $event->mode === SeederExecutionMode::Scheduled,
        );
    }
}

final class EventFixtureSeeder extends Seeder
{
    public function run(): void
    {
        // Executed by the executor — Seeder::__invoke() requires run().
        usleep(0);
    }
}

final class ExplodingEventSeeder extends Seeder
{
    public function run(): never
    {
        throw new RuntimeException('boom');
    }
}
