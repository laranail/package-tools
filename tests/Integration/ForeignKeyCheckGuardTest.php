<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Integration;

use Mockery;
use RuntimeException;
use InvalidArgumentException;
use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Simtabi\Laranail\Package\Tools\Support\ForeignKeyCheckGuard;

final class ForeignKeyCheckGuardTest extends TestCase
{
    public function test_run_returns_callback_value(): void
    {
        $guard = new ForeignKeyCheckGuard;

        $value = $guard->run(static fn (): int => 42);

        $this->assertSame(42, $value);
        $this->assertSame(0, $guard->depth());
    }

    public function test_nested_calls_only_toggle_schema_once(): void
    {
        $guard = new ForeignKeyCheckGuard;
        $observed = [];

        $guard->run(function () use ($guard, &$observed): void {
            $observed[] = $guard->depth();
            $guard->run(function () use ($guard, &$observed): void {
                $observed[] = $guard->depth();
            });
            $observed[] = $guard->depth();
        });

        $this->assertSame([1, 2, 1], $observed);
        $this->assertSame(0, $guard->depth());
    }

    public function test_callback_exception_still_restores_depth(): void
    {
        $guard = new ForeignKeyCheckGuard;

        try {
            $guard->run(static function (): never {
                throw new RuntimeException('seeder blew up');
            });
            $this->fail('expected exception was not thrown');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(0, $guard->depth());
    }

    public function test_sqlite_really_switches_foreign_keys_off(): void
    {
        config(['database.default' => 'testing', 'database.connections.testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
        Schema::create('fk_parents', static fn (Blueprint $t) => $t->id());
        Schema::create('fk_children', static function (Blueprint $t): void {
            $t->id();
            $t->foreignId('parent_id')->constrained('fk_parents');
        });

        (new ForeignKeyCheckGuard)->run(static fn () => DB::table('fk_children')->insert(['parent_id' => 999]));
        $this->assertSame(1, DB::table('fk_children')->count());

        $this->expectException(QueryException::class);
        DB::table('fk_children')->insert(['parent_id' => 998]);
    }

    public function test_postgres_replica_mode_toggles_the_replication_role(): void
    {
        config(['laranail.package-tools.seeders.postgres_foreign_keys' => ForeignKeyCheckGuard::REPLICA]);
        $this->fakePostgres();

        DB::shouldReceive('statement')->once()->ordered()->with('SET session_replication_role = replica')->andReturn(true);
        DB::shouldReceive('statement')->once()->ordered()->with('SET session_replication_role = DEFAULT')->andReturn(true);

        $this->assertSame('ok', (new ForeignKeyCheckGuard)->run(static fn (): string => 'ok'));
    }

    public function test_postgres_replica_mode_fails_loudly_without_privilege(): void
    {
        config(['laranail.package-tools.seeders.postgres_foreign_keys' => ForeignKeyCheckGuard::REPLICA]);
        $this->fakePostgres();

        DB::shouldReceive('statement')->once()->with('SET session_replication_role = replica')
            ->andThrow(new RuntimeException('permission denied to set parameter "session_replication_role"'));

        $guard = new ForeignKeyCheckGuard;
        $ran = false;

        try {
            $guard->run(static function () use (&$ran): void {
                $ran = true;
            });
            $this->fail('expected the guard to refuse');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString("postgres_foreign_keys = 'replica' needs", $e->getMessage());
        }

        $this->assertFalse($ran, 'the bundle must not run without the foreign-key state it asked for');
        $this->assertSame(0, $guard->depth());
    }

    public function test_postgres_defer_mode_uses_the_schema_builder(): void
    {
        // Schema first: mocking it resolves the real schema builder, which needs
        // the real connection before fakePostgres() replaces it.
        Schema::shouldReceive('disableForeignKeyConstraints')->once();
        Schema::shouldReceive('enableForeignKeyConstraints')->once();
        $this->fakePostgres();
        DB::shouldReceive('statement')->never();

        (new ForeignKeyCheckGuard)->run(static fn (): null => null);

        $this->assertSame(ForeignKeyCheckGuard::DEFER, ForeignKeyCheckGuard::postgresMode());
    }

    public function test_an_unknown_mode_is_a_configuration_error(): void
    {
        config(['laranail.package-tools.seeders.postgres_foreign_keys' => 'off']);

        $this->expectException(InvalidArgumentException::class);
        ForeignKeyCheckGuard::postgresMode();
    }

    private function fakePostgres(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('getDriverName')->andReturn('pgsql');
        DB::shouldReceive('connection')->andReturn($connection);
    }
}
