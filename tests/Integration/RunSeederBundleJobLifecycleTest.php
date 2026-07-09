<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Integration;

use Illuminate\Database\Seeder;
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Facades\Event;
use Orchestra\Testbench\TestCase;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Enums\FailureReason;
use Simtabi\Laranail\Package\Tools\Enums\PackageActionType;
use Simtabi\Laranail\Package\Tools\Enums\SeederExecutionMode;
use Simtabi\Laranail\Package\Tools\Events\PackageActionFailed;
use Simtabi\Laranail\Package\Tools\Events\PackageActionStarted;
use Simtabi\Laranail\Package\Tools\Events\PackageActionSucceeded;
use Simtabi\Laranail\Package\Tools\Jobs\RunSeederBundleJob;
use Simtabi\Laranail\Package\Tools\Providers\PackageToolsServiceProvider;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederAutorun;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederExecutor;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederRegistry;

/**
 * The queued job now reports a full Job lifecycle: Started/Succeeded on a
 * clean run, a Job/Failed when the seeders inside it fail, a Job/Cancelled
 * when the bundle key no longer resolves, and — via failed() — a Job/Failed
 * (TimedOut for a queue timeout) when the framework marks the job failed.
 */
final class RunSeederBundleJobLifecycleTest extends TestCase
{
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

    public function test_a_clean_run_emits_job_started_and_succeeded(): void
    {
        Event::fake([PackageActionStarted::class, PackageActionSucceeded::class, PackageActionFailed::class]);

        $this->app->make(SeederRegistry::class)->register('t/ok', [JobLifecycleOkSeeder::class]);
        $this->handle(new RunSeederBundleJob('t/ok'));

        Event::assertDispatched(PackageActionStarted::class, fn (PackageActionStarted $e): bool => $e->type === PackageActionType::Job && $e->action === 't/ok');
        Event::assertDispatched(PackageActionSucceeded::class, fn (PackageActionSucceeded $e): bool => $e->type === PackageActionType::Job && $e->action === 't/ok');
        Event::assertNotDispatched(PackageActionFailed::class);
    }

    public function test_seeders_failing_inside_the_job_emit_a_job_failed(): void
    {
        Event::fake([PackageActionFailed::class, PackageActionSucceeded::class]);

        $this->app->make(SeederRegistry::class)->register('t/boom', [JobLifecycleBoomSeeder::class]);
        $this->handle(new RunSeederBundleJob('t/boom'));

        // The per-seeder Seeder/Failed AND a bundle-level Job/Failed.
        Event::assertDispatched(PackageActionFailed::class, fn (PackageActionFailed $e): bool => $e->type === PackageActionType::Job && $e->action === 't/boom');
        Event::assertDispatched(PackageActionFailed::class, fn (PackageActionFailed $e): bool => $e->type === PackageActionType::Seeder);
        Event::assertNotDispatched(PackageActionSucceeded::class);
    }

    public function test_a_vanished_bundle_key_emits_job_cancelled(): void
    {
        Event::fake([PackageActionFailed::class]);

        $this->handle(new RunSeederBundleJob('t/missing'));

        Event::assertDispatched(PackageActionFailed::class, fn (PackageActionFailed $e): bool => $e->type === PackageActionType::Job && $e->reason === FailureReason::Cancelled && $e->action === 't/missing');
    }

    public function test_failed_hook_classifies_a_queue_timeout_as_timed_out(): void
    {
        Event::fake([PackageActionFailed::class]);

        (new RunSeederBundleJob('t/slow', SeederExecutionMode::Queued))
            ->failed(new TimeoutExceededException('timed out'));

        Event::assertDispatched(PackageActionFailed::class, fn (PackageActionFailed $e): bool => $e->type === PackageActionType::Job && $e->reason === FailureReason::TimedOut && $e->action === 't/slow');
    }

    public function test_failed_hook_classifies_a_generic_error_as_failed(): void
    {
        Event::fake([PackageActionFailed::class]);

        (new RunSeederBundleJob('t/broke'))->failed(new RuntimeException('kaboom'));

        Event::assertDispatched(PackageActionFailed::class, fn (PackageActionFailed $e): bool => $e->type === PackageActionType::Job && $e->reason === FailureReason::Failed && $e->exceptionClass === RuntimeException::class);
    }
}

final class JobLifecycleOkSeeder extends Seeder
{
    public function run(): void
    {
        usleep(0);
    }
}

final class JobLifecycleBoomSeeder extends Seeder
{
    public function run(): never
    {
        throw new RuntimeException('boom');
    }
}
