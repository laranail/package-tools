<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Tests\Integration;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Event;
use Orchestra\Testbench\TestCase;
use RuntimeException;
use Simtabi\Laranail\PackageTools\Events\SeedingFinished;
use Simtabi\Laranail\PackageTools\Events\SeedingStarted;
use Simtabi\Laranail\PackageTools\Services\Database\SeederExecutor;
use Simtabi\Laranail\PackageTools\Services\Database\SeederRegistry;

final class SeederExecutorTest extends TestCase
{
    public function test_run_executes_every_registered_seeder(): void
    {
        StubCounter::reset();
        $registry = (new SeederRegistry)
            ->register('vendor/a', [StubSeederA::class], 'Vendor\\A')
            ->register('vendor/b', [StubSeederB::class], 'Vendor\\B');

        $stats = (new SeederExecutor($this->app))->run($registry);

        $this->assertSame(2, $stats['success']);
        $this->assertSame(0, $stats['failed']);
        $this->assertSame([StubSeederA::class, StubSeederB::class], $stats['executed']);
        $this->assertSame(['A', 'B'], StubCounter::$ran);
    }

    public function test_run_continues_past_failing_seeder_and_logs(): void
    {
        StubCounter::reset();
        $registry = (new SeederRegistry)
            ->register('vendor/a', [StubSeederA::class, ThrowingSeeder::class, StubSeederB::class]);

        $stats = (new SeederExecutor($this->app))->run($registry);

        $this->assertSame(2, $stats['success']);
        $this->assertSame(1, $stats['failed']);
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

    public function test_run_returns_zeroes_for_empty_registry(): void
    {
        $stats = (new SeederExecutor($this->app))->run(new SeederRegistry);

        $this->assertSame(0, $stats['success']);
        $this->assertSame(0, $stats['failed']);
        $this->assertSame([], $stats['executed']);
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
