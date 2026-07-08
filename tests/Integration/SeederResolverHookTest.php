<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Integration;

use Illuminate\Database\Seeder;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederAutorun;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederExecutor;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederRegistry;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederResolverHook;

final class SeederResolverHookTest extends TestCase
{
    private function makeHook(SeederRegistry $registry, ?SeederAutorun $autorun = null): SeederResolverHook
    {
        $executor = new SeederExecutor($this->app);

        return new SeederResolverHook(
            $this->app,
            $registry,
            $executor,
            $autorun ?? new SeederAutorun($this->app, $registry, $executor),
        );
    }

    public function test_resolving_root_seeder_runs_registered_seeders(): void
    {
        HookStubCounter::reset();
        $registry = (new SeederRegistry)
            ->register('vendor/a', [HookStubSeederA::class], 'Vendor\\A')
            ->register('vendor/b', [HookStubSeederB::class], 'Vendor\\B');

        $hook = $this->makeHook($registry);
        $hook->attach(RootDatabaseSeeder::class);

        $this->assertTrue($hook->isAttached());
        $this->assertSame([], HookStubCounter::$ran);

        $this->app->make(RootDatabaseSeeder::class);

        $this->assertSame(['A', 'B'], HookStubCounter::$ran);
    }

    public function test_hook_fires_only_once_even_across_multiple_resolutions(): void
    {
        HookStubCounter::reset();
        $registry = (new SeederRegistry)
            ->register('vendor/a', [HookStubSeederA::class]);

        $hook = $this->makeHook($registry);
        $hook->attach(RootDatabaseSeeder::class);

        $this->app->make(RootDatabaseSeeder::class);
        $this->app->make(RootDatabaseSeeder::class);
        $this->app->make(RootDatabaseSeeder::class);

        $this->assertSame(['A'], HookStubCounter::$ran);
    }

    public function test_attach_is_idempotent_and_does_not_stack_listeners(): void
    {
        HookStubCounter::reset();
        $registry = (new SeederRegistry)
            ->register('vendor/a', [HookStubSeederA::class]);

        $hook = $this->makeHook($registry);
        $hook->attach(RootDatabaseSeeder::class);
        $hook->attach(RootDatabaseSeeder::class);
        $hook->attach(RootDatabaseSeeder::class);

        $this->app->make(RootDatabaseSeeder::class);

        $this->assertSame(['A'], HookStubCounter::$ran);
    }

    public function test_resolving_an_arbitrary_seeder_does_not_trigger_the_hook(): void
    {
        HookStubCounter::reset();
        $registry = (new SeederRegistry)
            ->register('vendor/a', [HookStubSeederA::class]);

        $hook = $this->makeHook($registry);
        $hook->attach(RootDatabaseSeeder::class);

        // 3.0 behavior: only EXACT root seeders trigger. An unrelated
        // Seeder subclass (db:seed --class=X, web-request resolution, the
        // executor's own make()) must NOT side-effect-run package bundles.
        $this->app->make(UnrelatedSeeder::class);

        $this->assertSame([], HookStubCounter::$ran);

        // The root seeder still triggers afterwards.
        $this->app->make(RootDatabaseSeeder::class);
        $this->assertSame(['A'], HookStubCounter::$ran);
    }

    public function test_bundles_already_in_the_ledger_are_skipped(): void
    {
        HookStubCounter::reset();
        $registry = (new SeederRegistry)
            ->register('vendor/a', [HookStubSeederA::class])
            ->register('vendor/b', [HookStubSeederB::class]);

        $executor = new SeederExecutor($this->app);
        $autorun = new SeederAutorun($this->app, $registry, $executor);
        $autorun->markExecuted('vendor/a');

        $hook = new SeederResolverHook($this->app, $registry, $executor, $autorun);
        $hook->attach(RootDatabaseSeeder::class);

        $this->app->make(RootDatabaseSeeder::class);

        // vendor/a was already executed (e.g. by post-migration autorun);
        // only vendor/b may run.
        $this->assertSame(['B'], HookStubCounter::$ran);
    }

    public function test_bundles_registered_after_first_resolution_still_run(): void
    {
        HookStubCounter::reset();
        $registry = (new SeederRegistry)
            ->register('vendor/a', [HookStubSeederA::class]);

        $hook = $this->makeHook($registry);
        $hook->attach(RootDatabaseSeeder::class);

        $this->app->make(RootDatabaseSeeder::class);
        $this->assertSame(['A'], HookStubCounter::$ran);

        // A bundle registered AFTER the first firing (late boot, dynamic
        // registration) is picked up on the next root resolution — the old
        // one-shot $fired flag lost these.
        $registry->register('vendor/b', [HookStubSeederB::class]);
        $this->app->make(RootDatabaseSeeder::class);

        $this->assertSame(['A', 'B'], HookStubCounter::$ran);
    }
}

final class HookStubCounter
{
    /** @var list<string> */
    public static array $ran = [];

    public static function reset(): void
    {
        self::$ran = [];
    }
}

final class HookStubSeederA extends Seeder
{
    public function run(): void
    {
        HookStubCounter::$ran[] = 'A';
    }
}

final class HookStubSeederB extends Seeder
{
    public function run(): void
    {
        HookStubCounter::$ran[] = 'B';
    }
}

class RootDatabaseSeeder extends Seeder
{
    public function run(): void {}
}

class UnrelatedSeeder extends Seeder
{
    public function run(): void {}
}
