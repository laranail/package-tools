<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Services\Database;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Event;
use Simtabi\Laranail\Package\Tools\Events\SeederExecuted;
use Simtabi\Laranail\Package\Tools\Events\SeederExecuting;
use Simtabi\Laranail\Package\Tools\Events\SeedingStarted;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederBundle;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederExecutor;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederRegistry;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

/**
 * cross-bundle execution order (priority ascending, ties keep registration
 * order) and per-bundle option isolation — one bundle's events/parameters
 * never leak into another's run.
 */
final class SeederExecutorPriorityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        PriorityRunLog::reset();
    }

    public function test_bundles_run_priority_ascending_with_ties_in_registration_order(): void
    {
        $registry = (new SeederRegistry)
            ->registerBundle(SeederBundle::make('vendor/high', [HighPrioritySeeder::class])->priority(5))
            ->registerBundle(SeederBundle::make('vendor/first', [FirstZeroSeeder::class])->priority(0))
            ->registerBundle(SeederBundle::make('vendor/second', [SecondZeroSeeder::class])->priority(0));

        (new SeederExecutor($this->app))->run($registry);

        // the two priority-0 bundles keep registration order, then the 5
        $this->assertSame(['first-zero', 'second-zero', 'high'], PriorityRunLog::$ran);
    }

    public function test_negative_priorities_run_before_zero(): void
    {
        $registry = (new SeederRegistry)
            ->registerBundle(SeederBundle::make('vendor/zero', [FirstZeroSeeder::class]))
            ->registerBundle(SeederBundle::make('vendor/early', [HighPrioritySeeder::class])->priority(-1));

        (new SeederExecutor($this->app))->run($registry);

        $this->assertSame(['high', 'first-zero'], PriorityRunLog::$ran);
    }

    public function test_parameters_are_scoped_to_their_own_bundle(): void
    {
        $registry = (new SeederRegistry)
            ->registerBundle(
                SeederBundle::make('vendor/a', [TenantAwareSeederA::class])
                    ->parameters(['tenant' => 'acme']),
            )
            ->registerBundle(SeederBundle::make('vendor/b', [TenantAwareSeederB::class]));

        $stats = (new SeederExecutor($this->app))->run($registry);

        $this->assertSame(2, $stats->success);
        $this->assertSame('acme', PriorityRunLog::$tenants[TenantAwareSeederA::class]);
        // bundle b passed no parameters, so b's seeder keeps its default
        $this->assertSame('unset', PriorityRunLog::$tenants[TenantAwareSeederB::class]);
    }

    public function test_per_seeder_events_fire_only_for_the_opted_in_bundle(): void
    {
        Event::fake();

        $registry = (new SeederRegistry)
            ->registerBundle(
                SeederBundle::make('vendor/a', [TenantAwareSeederA::class])
                    ->inNamespace('Vendor\\A')
                    ->firesEvents(),
            )
            ->registerBundle(
                SeederBundle::make('vendor/b', [TenantAwareSeederB::class])->inNamespace('Vendor\\B'),
            );

        (new SeederExecutor($this->app))->run($registry);

        Event::assertDispatched(
            SeederExecuting::class,
            fn (SeederExecuting $e): bool => $e->seederClass === TenantAwareSeederA::class,
        );
        Event::assertDispatched(
            SeederExecuted::class,
            fn (SeederExecuted $e): bool => $e->seederClass === TenantAwareSeederA::class,
        );
        Event::assertNotDispatched(
            SeederExecuting::class,
            fn (SeederExecuting $e): bool => $e->seederClass === TenantAwareSeederB::class,
        );
        Event::assertNotDispatched(
            SeederExecuted::class,
            fn (SeederExecuted $e): bool => $e->seederClass === TenantAwareSeederB::class,
        );

        // the session-level start event still names every group in the run
        Event::assertDispatched(
            SeedingStarted::class,
            fn (SeedingStarted $e): bool => $e->packages === ['Vendor\\A', 'Vendor\\B'],
        );
    }
}

final class PriorityRunLog
{
    /** @var list<string> */
    public static array $ran = [];

    /** @var array<class-string, string> */
    public static array $tenants = [];

    public static function reset(): void
    {
        self::$ran = [];
        self::$tenants = [];
    }
}

final class FirstZeroSeeder extends Seeder
{
    public function run(): void
    {
        PriorityRunLog::$ran[] = 'first-zero';
    }
}

final class SecondZeroSeeder extends Seeder
{
    public function run(): void
    {
        PriorityRunLog::$ran[] = 'second-zero';
    }
}

final class HighPrioritySeeder extends Seeder
{
    public function run(): void
    {
        PriorityRunLog::$ran[] = 'high';
    }
}

final class TenantAwareSeederA extends Seeder
{
    public function __construct(private readonly string $tenant = 'unset') {}

    public function run(): void
    {
        PriorityRunLog::$tenants[self::class] = $this->tenant;
    }
}

final class TenantAwareSeederB extends Seeder
{
    public function __construct(private readonly string $tenant = 'unset') {}

    public function run(): void
    {
        PriorityRunLog::$tenants[self::class] = $this->tenant;
    }
}
