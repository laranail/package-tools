<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Integration;

use Illuminate\Database\Seeder;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederExecutor;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederRegistry;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederResolverHook;

final class SeederResolverHookTest extends TestCase
{
    public function test_resolving_database_seeder_runs_registered_seeders(): void
    {
        HookStubCounter::reset();
        $registry = (new SeederRegistry)
            ->register('vendor/a', [HookStubSeederA::class], 'Vendor\\A')
            ->register('vendor/b', [HookStubSeederB::class], 'Vendor\\B');

        $hook = new SeederResolverHook($this->app, $registry, new SeederExecutor($this->app));
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

        $hook = new SeederResolverHook($this->app, $registry, new SeederExecutor($this->app));
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

        $hook = new SeederResolverHook($this->app, $registry, new SeederExecutor($this->app));
        $hook->attach(RootDatabaseSeeder::class);
        $hook->attach(RootDatabaseSeeder::class);
        $hook->attach(RootDatabaseSeeder::class);

        $this->app->make(RootDatabaseSeeder::class);

        $this->assertSame(['A'], HookStubCounter::$ran);
    }

    public function test_resolving_via_base_seeder_contract_triggers_the_hook(): void
    {
        HookStubCounter::reset();
        $registry = (new SeederRegistry)
            ->register('vendor/a', [HookStubSeederA::class]);

        $hook = new SeederResolverHook($this->app, $registry, new SeederExecutor($this->app));
        $hook->attach(RootDatabaseSeeder::class);

        // Resolving any Seeder subclass goes through the Seeder::class
        // resolving callback the hook also registers.
        $this->app->make(RootDatabaseSeeder::class);

        $this->assertSame(['A'], HookStubCounter::$ran);
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
