<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Integration;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Event;
use Orchestra\Testbench\TestCase;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Events\SeedingFinished;
use Simtabi\Laranail\Package\Tools\Events\SeedingStarted;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederExecutor;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederRegistry;

final class SeederExecutorTest extends TestCase
{
    public function test_run_executes_every_registered_seeder(): void
    {
        StubCounter::reset();
        $registry = (new SeederRegistry)
            ->register('vendor/a', [StubSeederA::class], 'Vendor\\A')
            ->register('vendor/b', [StubSeederB::class], 'Vendor\\B');

        $stats = (new SeederExecutor($this->app))->run($registry);

        $this->assertSame(2, $stats->success);
        $this->assertSame(0, $stats->failed);
        $this->assertSame(2, $stats->total);
        $this->assertSame(['A', 'B'], StubCounter::$ran);
    }

    public function test_run_continues_past_failing_seeder_and_logs(): void
    {
        StubCounter::reset();
        $registry = (new SeederRegistry)
            ->register('vendor/a', [StubSeederA::class, ThrowingSeeder::class, StubSeederB::class]);

        $stats = (new SeederExecutor($this->app))->run($registry);

        $this->assertSame(2, $stats->success);
        $this->assertSame(1, $stats->failed);
        $this->assertSame(['A', 'B'], StubCounter::$ran);
    }

    public function test_run_emits_lifecycle_events_when_opted_in(): void
    {
        Event::fake();
        StubCounter::reset();
        $registry = (new SeederRegistry)
            ->register('vendor/a', [StubSeederA::class], 'Vendor\\A', ['fire_events' => true]);

        (new SeederExecutor($this->app))->run($registry);

        Event::assertDispatched(SeedingStarted::class, fn ($e): bool => $e->packages === ['Vendor\\A']);
        Event::assertDispatched(
            SeedingFinished::class,
            fn ($e): bool => $e->packages === ['Vendor\\A']
                && $e->successCount === 1
                && $e->failureCount === 0,
        );
    }

    public function test_run_does_not_emit_events_by_default(): void
    {
        Event::fake();
        $registry = (new SeederRegistry)
            ->register('vendor/a', [StubSeederA::class]);

        (new SeederExecutor($this->app))->run($registry);

        Event::assertNotDispatched(SeedingStarted::class);
        Event::assertNotDispatched(SeedingFinished::class);
    }

    public function test_seeders_receive_method_injection_in_run(): void
    {
        StubCounter::reset();
        $this->app->singleton(SeededMarkerService::class);

        $registry = (new SeederRegistry)
            ->register('vendor/di', [DependencyInjectedSeeder::class]);

        $stats = (new SeederExecutor($this->app))->run($registry);

        $this->assertSame(1, $stats->success);
        $this->assertTrue($this->app->make(SeededMarkerService::class)->touched);
    }

    public function test_seeders_can_use_this_call(): void
    {
        StubCounter::reset();
        $registry = (new SeederRegistry)
            ->register('vendor/caller', [CallingSeeder::class]);

        $stats = (new SeederExecutor($this->app))->run($registry);

        $this->assertSame(1, $stats->success);
        $this->assertSame(['A', 'CALLER'], StubCounter::$ran);
    }

    public function test_stop_on_failure_skips_the_rest_of_the_bundle_only(): void
    {
        StubCounter::reset();
        $registry = (new SeederRegistry)
            ->register('vendor/fragile', [StubSeederA::class, ThrowingSeeder::class, StubSeederB::class], null, [
                'stop_on_failure' => true,
            ])
            ->register('vendor/other', [StubSeederC::class]);

        $stats = (new SeederExecutor($this->app))->run($registry);

        // B is skipped after the failure; the OTHER bundle still runs.
        $this->assertSame(['A', 'C'], StubCounter::$ran);
        $this->assertSame(1, $stats->failed);
    }

    public function test_run_returns_zeroes_for_empty_registry(): void
    {
        $stats = (new SeederExecutor($this->app))->run(new SeederRegistry);

        $this->assertSame(0, $stats->success);
        $this->assertSame(0, $stats->failed);
        $this->assertTrue($stats->isEmpty());
    }
}

final class SeededMarkerService
{
    public bool $touched = false;
}

final class DependencyInjectedSeeder extends Seeder
{
    // 3.0: the executor injects the container, so run()-signature DI works
    // exactly like Laravel's own db:seed path.
    public function run(SeededMarkerService $service): void
    {
        $service->touched = true;
        StubCounter::$ran[] = 'DI';
    }
}

final class CallingSeeder extends Seeder
{
    public function run(): void
    {
        // $this->call() requires the container the executor now injects.
        $this->call(StubSeederA::class, true);
        StubCounter::$ran[] = 'CALLER';
    }
}

final class StubSeederC extends Seeder
{
    public function run(): void
    {
        StubCounter::$ran[] = 'C';
    }
}

final class StubCounter
{
    /** @var list<string> */
    public static array $ran = [];

    public static function reset(): void
    {
        self::$ran = [];
    }
}

final class StubSeederA extends Seeder
{
    public function run(): void
    {
        StubCounter::$ran[] = 'A';
    }
}

final class StubSeederB extends Seeder
{
    public function run(): void
    {
        StubCounter::$ran[] = 'B';
    }
}

final class ThrowingSeeder extends Seeder
{
    public function run(): never
    {
        throw new RuntimeException('boom');
    }
}
