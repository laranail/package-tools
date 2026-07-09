<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Orchestra\Testbench\TestCase;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Enums\FailureReason;
use Simtabi\Laranail\Package\Tools\Enums\PackageActionType;
use Simtabi\Laranail\Package\Tools\Events\PackageActionFailed;
use Simtabi\Laranail\Package\Tools\Events\PackageActionStarted;
use Simtabi\Laranail\Package\Tools\Events\PackageActionSucceeded;
use Simtabi\Laranail\Package\Tools\Events\PackageSeedingFailed;
use Simtabi\Laranail\Package\Tools\Providers\PackageToolsServiceProvider;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederExecutor;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederRegistry;

/**
 * The unified PackageAction{Started,Succeeded,Failed} lifecycle layer for
 * seeders, dispatched alongside — never instead of — the 8 bespoke seeder
 * events (which stay untouched for BC).
 */
final class PackageActionLifecycleEventsTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [PackageToolsServiceProvider::class];
    }

    public function test_a_clean_bundle_emits_started_then_succeeded(): void
    {
        Event::fake([PackageActionStarted::class, PackageActionSucceeded::class, PackageActionFailed::class]);

        $registry = (new SeederRegistry)->register('acme/blog', [LifecycleOkSeeder::class], 'Acme\\Blog');
        (new SeederExecutor($this->app))->run($registry);

        Event::assertDispatched(PackageActionStarted::class, fn (PackageActionStarted $e): bool => $e->type === PackageActionType::Seeder && $e->action === 'acme/blog' && $e->packageName === 'Acme\\Blog');
        Event::assertDispatched(PackageActionSucceeded::class, fn (PackageActionSucceeded $e): bool => $e->type === PackageActionType::Seeder && $e->action === 'acme/blog' && $e->durationMs !== null);
        Event::assertNotDispatched(PackageActionFailed::class);
    }

    public function test_a_failing_seeder_emits_failed_without_succeeded_and_keeps_the_classic_event(): void
    {
        Event::fake([PackageActionFailed::class, PackageActionSucceeded::class, PackageSeedingFailed::class]);

        $registry = (new SeederRegistry)->register('acme/fragile', [LifecycleBoomSeeder::class], 'Acme\\Fragile');
        (new SeederExecutor($this->app))->run($registry);

        Event::assertDispatched(PackageActionFailed::class, fn (PackageActionFailed $e): bool => $e->type === PackageActionType::Seeder
            && $e->reason === FailureReason::Failed
            && $e->exceptionClass === RuntimeException::class
            && $e->message === 'boom');
        // No unified success when the bundle had a failure.
        Event::assertNotDispatched(PackageActionSucceeded::class);
        // BC: the bespoke seeder event still fires alongside.
        Event::assertDispatched(PackageSeedingFailed::class, fn (PackageSeedingFailed $e): bool => $e->seederClass === LifecycleBoomSeeder::class);
    }

    public function test_stop_on_failure_reports_the_skipped_seeders_as_interrupted(): void
    {
        Event::fake([PackageActionFailed::class]);

        $registry = (new SeederRegistry)->register(
            'acme/chain',
            [LifecycleBoomSeeder::class, LifecycleOkSeeder::class],
            'Acme\\Chain',
            ['stop_on_failure' => true],
        );
        (new SeederExecutor($this->app))->run($registry);

        Event::assertDispatched(PackageActionFailed::class, fn (PackageActionFailed $e): bool => $e->reason === FailureReason::Failed);
        Event::assertDispatched(PackageActionFailed::class, fn (PackageActionFailed $e): bool => $e->reason === FailureReason::Interrupted && $e->action === LifecycleOkSeeder::class);
    }

    public function test_an_overlap_locked_bundle_is_reported_as_cancelled(): void
    {
        Event::fake([PackageActionFailed::class, PackageActionStarted::class]);

        // Pre-acquire the bundle's overlap lock so the executor sees it held.
        $held = Cache::lock('package-tools:seeding:acme/locked', 60);
        $this->assertTrue($held->get());

        $registry = (new SeederRegistry)->register(
            'acme/locked',
            [LifecycleOkSeeder::class],
            'Acme\\Locked',
            ['without_overlapping' => 5],
        );
        (new SeederExecutor($this->app))->run($registry);

        Event::assertDispatched(PackageActionFailed::class, fn (PackageActionFailed $e): bool => $e->reason === FailureReason::Cancelled && $e->action === 'acme/locked');
        // The bundle never started.
        Event::assertNotDispatched(PackageActionStarted::class);

        $held->release();
    }

    public function test_the_lifecycle_gate_silences_the_unified_layer(): void
    {
        config()->set('package-tools.events.lifecycle.enabled', false);
        Event::fake([PackageActionStarted::class, PackageActionSucceeded::class]);

        $registry = (new SeederRegistry)->register('acme/blog', [LifecycleOkSeeder::class]);
        (new SeederExecutor($this->app))->run($registry);

        Event::assertNotDispatched(PackageActionStarted::class);
        Event::assertNotDispatched(PackageActionSucceeded::class);
    }
}

final class LifecycleOkSeeder extends Seeder
{
    public function run(): void
    {
        usleep(0);
    }
}

final class LifecycleBoomSeeder extends Seeder
{
    public function run(): never
    {
        throw new RuntimeException('boom');
    }
}
