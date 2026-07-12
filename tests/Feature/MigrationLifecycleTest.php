<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\MigrationEnded;
use Illuminate\Database\Events\MigrationStarted;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Event;
use Orchestra\Testbench\TestCase;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Enums\FailureReason;
use Simtabi\Laranail\Package\Tools\Enums\PackageActionType;
use Simtabi\Laranail\Package\Tools\Events\PackageActionFailed;
use Simtabi\Laranail\Package\Tools\Events\PackageActionStarted;
use Simtabi\Laranail\Package\Tools\Events\PackageActionSucceeded;
use Simtabi\Laranail\Package\Tools\Providers\PackageToolsServiceProvider;
use Simtabi\Laranail\Package\Tools\Services\Database\FailureAwareMigrator;
use Simtabi\Laranail\Package\Tools\Services\Database\MigrationFailureDetector;
use Throwable;

/**
 * Full-fidelity migration lifecycle. The decorated {@see FailureAwareMigrator}
 * emits Started/Succeeded/Failed (with the real migration name + exception)
 * for migrations run through `artisan migrate`; the conflict-free
 * {@see MigrationFailureDetector} does the same from events when another
 * package has already decorated the migrator.
 */
final class MigrationLifecycleTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [PackageToolsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'lifecycle');
        $app['config']->set('database.connections.lifecycle', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    private function migrate(string $path): void
    {
        try {
            $this->app->make(Kernel::class)->call('migrate', [
                '--path' => $path,
                '--realpath' => true,
            ]);
        } catch (Throwable) {
            // A throwing migration aborts migrate loudly; we assert on events.
        }
    }

    public function test_the_migrator_is_decorated_in_console(): void
    {
        $this->assertInstanceOf(FailureAwareMigrator::class, $this->app->make('migrator'));
    }

    public function test_a_successful_migration_emits_started_then_succeeded(): void
    {
        Event::fake([PackageActionStarted::class, PackageActionSucceeded::class, PackageActionFailed::class]);

        $this->migrate(__DIR__ . '/../fixtures/migrations-lifecycle-ok');

        Event::assertDispatched(PackageActionStarted::class, fn (PackageActionStarted $e): bool => $e->type === PackageActionType::Migration && str_contains($e->action, 'lifecycle_ok'));
        Event::assertDispatched(PackageActionSucceeded::class, fn (PackageActionSucceeded $e): bool => $e->type === PackageActionType::Migration && str_contains($e->action, 'lifecycle_ok'));
        Event::assertNotDispatched(PackageActionFailed::class);
    }

    public function test_a_throwing_migration_emits_failed_with_the_real_exception(): void
    {
        Event::fake([PackageActionFailed::class, PackageActionSucceeded::class]);

        $this->migrate(__DIR__ . '/../fixtures/migrations-lifecycle-boom');

        Event::assertDispatched(PackageActionFailed::class, fn (PackageActionFailed $e): bool => $e->type === PackageActionType::Migration
            && $e->reason === FailureReason::Failed
            && $e->exceptionClass === RuntimeException::class
            && $e->message === 'migration exploded'
            && str_contains($e->action, 'lifecycle_boom'));
        Event::assertNotDispatched(PackageActionSucceeded::class);
    }

    public function test_the_detector_reports_an_in_flight_migration_as_failed_on_shutdown(): void
    {
        $captured = [];
        Event::listen(PackageActionFailed::class, function (PackageActionFailed $e) use (&$captured): void {
            $captured[] = $e;
        });

        $detector = $this->app->make(MigrationFailureDetector::class);
        $detector->register($this->app['events'], $this->app);

        $migration = new class extends Migration {};
        // Started fires, Ended never does (the migration "threw").
        Event::dispatch(new MigrationStarted($migration, 'up', '2024_01_01_000009_boom'));

        $this->app->terminate();

        $this->assertCount(1, $captured);
        $this->assertSame(PackageActionType::Migration, $captured[0]->type);
        $this->assertSame(FailureReason::Failed, $captured[0]->reason);
        $this->assertSame('2024_01_01_000009_boom', $captured[0]->action);
    }

    public function test_the_detector_reports_a_prior_in_flight_failure_when_a_new_migration_starts(): void
    {
        $failed = [];
        Event::listen(PackageActionFailed::class, function (PackageActionFailed $e) use (&$failed): void {
            $failed[] = $e->action;
        });

        $detector = $this->app->make(MigrationFailureDetector::class);
        $detector->register($this->app['events'], $this->app);

        $migration = new class extends Migration {};
        // Migration B starts and throws (no MigrationEnded); then, in the same
        // process, migration C starts — B's failure must not be swallowed.
        Event::dispatch(new MigrationStarted($migration, 'up', 'B_migration'));
        Event::dispatch(new MigrationStarted($migration, 'up', 'C_migration'));

        $this->assertContains('B_migration', $failed);

        // C never ends either → flushed on shutdown.
        $this->app->terminate();
        $this->assertContains('C_migration', $failed);
    }

    public function test_the_detector_reports_a_completed_migration_as_succeeded(): void
    {
        $captured = [];
        Event::listen(PackageActionSucceeded::class, function (PackageActionSucceeded $e) use (&$captured): void {
            $captured[] = $e;
        });

        $detector = $this->app->make(MigrationFailureDetector::class);
        $detector->register($this->app['events'], $this->app);

        $migration = new class extends Migration {};
        Event::dispatch(new MigrationStarted($migration, 'up', '2024_01_01_000010_ok'));
        Event::dispatch(new MigrationEnded($migration, 'up', '2024_01_01_000010_ok'));

        $this->app->terminate();

        $this->assertCount(1, $captured);
        $this->assertSame('2024_01_01_000010_ok', $captured[0]->action);
    }
}
