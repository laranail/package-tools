<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Services\Event;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Orchestra\Testbench\TestCase;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Enums\FailureReason;
use Simtabi\Laranail\Package\Tools\Enums\PackageActionType;
use Simtabi\Laranail\Package\Tools\Events\PackageActionFailed;
use Simtabi\Laranail\Package\Tools\Events\PackageActionStarted;
use Simtabi\Laranail\Package\Tools\Events\PackageActionSucceeded;
use Simtabi\Laranail\Package\Tools\Providers\PackageToolsServiceProvider;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederRunTracker;
use Simtabi\Laranail\Package\Tools\Services\Event\PackageActionReporter;

/**
 * The reporter is the single lifecycle choke point: it always logs failures
 * (at error), logs the start/success stream at debug, dispatches only behind
 * the matching config gate, wires the seeder tracker, and never rethrows.
 */
final class PackageActionReporterTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [PackageToolsServiceProvider::class];
    }

    private function reporter(): PackageActionReporter
    {
        return $this->app->make(PackageActionReporter::class);
    }

    public function test_a_failure_is_always_logged_at_error(): void
    {
        Log::spy();

        $this->reporter()->fail(PackageActionType::Custom, 'thing', null, 'it broke');

        Log::shouldHaveReceived('log')
            ->withArgs(fn (string $level): bool => $level === 'error')
            ->once();
    }

    public function test_lifecycle_is_logged_at_debug(): void
    {
        Log::spy();

        $this->reporter()->started(PackageActionType::Custom, 'thing');
        $this->reporter()->success(PackageActionType::Custom, 'thing');

        Log::shouldHaveReceived('log')
            ->withArgs(fn (string $level): bool => $level === 'debug')
            ->twice();
    }

    public function test_from_throwable_classifies_and_dispatches_failed(): void
    {
        Event::fake([PackageActionFailed::class]);

        $event = $this->reporter()->fromThrowable(
            PackageActionType::Seeder,
            'acme/blog',
            'Acme\\Blog',
            new RuntimeException('boom'),
        );

        $this->assertSame(FailureReason::Failed, $event->reason);
        $this->assertSame(RuntimeException::class, $event->exceptionClass);
        $this->assertSame('boom', $event->message);
        Event::assertDispatched(PackageActionFailed::class, fn (PackageActionFailed $e): bool => $e->type === PackageActionType::Seeder && $e->action === 'acme/blog');
    }

    public function test_the_lifecycle_gate_suppresses_dispatch_only(): void
    {
        config()->set('package-tools.events.lifecycle.enabled', false);
        Event::fake([PackageActionStarted::class, PackageActionSucceeded::class]);
        Log::spy();

        $this->reporter()->started(PackageActionType::Custom, 'thing');
        $this->reporter()->success(PackageActionType::Custom, 'thing');

        Event::assertNotDispatched(PackageActionStarted::class);
        Event::assertNotDispatched(PackageActionSucceeded::class);
        // …but the lines were still logged.
        Log::shouldHaveReceived('log')->twice();
    }

    public function test_the_failures_gate_suppresses_dispatch_but_never_the_log(): void
    {
        config()->set('package-tools.events.failures.enabled', false);
        Event::fake([PackageActionFailed::class]);
        Log::spy();

        $this->reporter()->fail(PackageActionType::Custom, 'thing', null, 'broke');

        Event::assertNotDispatched(PackageActionFailed::class);
        Log::shouldHaveReceived('log')->withArgs(fn (string $level): bool => $level === 'error')->once();
    }

    public function test_reporting_never_rethrows_even_when_a_listener_throws(): void
    {
        Event::listen(PackageActionFailed::class, function (): never {
            throw new RuntimeException('listener blew up');
        });

        // Must not propagate the listener's exception.
        $event = $this->reporter()->fail(PackageActionType::Custom, 'thing', null, 'broke');

        $this->assertInstanceOf(PackageActionFailed::class, $event);
    }

    public function test_lifecycle_reporting_never_rethrows_even_when_a_listener_throws(): void
    {
        Event::listen(PackageActionStarted::class, function (): never {
            throw new RuntimeException('started listener blew up');
        });
        Event::listen(PackageActionSucceeded::class, function (): never {
            throw new RuntimeException('succeeded listener blew up');
        });

        // A throwing lifecycle listener must not propagate (it would otherwise
        // abort an already-applied migration whose Started/Succeeded fired).
        $started = $this->reporter()->started(PackageActionType::Migration, '2024_x_create');
        $succeeded = $this->reporter()->success(PackageActionType::Migration, '2024_x_create');

        $this->assertInstanceOf(PackageActionStarted::class, $started);
        $this->assertInstanceOf(PackageActionSucceeded::class, $succeeded);
    }

    public function test_a_failure_carrying_a_bundle_key_updates_the_seeder_tracker(): void
    {
        $tracker = $this->app->make(SeederRunTracker::class);
        $tracker->start('acme/blog', 2);

        $this->reporter()->fail(
            PackageActionType::Seeder,
            'acme/blog',
            'Acme\\Blog',
            'kaboom',
            FailureReason::Failed,
            context: ['bundleKey' => 'acme/blog'],
        );

        $state = $tracker->get('acme/blog');
        $this->assertNotNull($state);
        $this->assertSame('kaboom', $state['message']);
    }

    public function test_for_package_returns_a_distinct_instance(): void
    {
        $base = $this->reporter();
        $scoped = $base->forPackage(null);

        $this->assertNotSame($base, $scoped);
    }

    public function test_track_emits_start_then_success_and_returns_the_result(): void
    {
        Event::fake([PackageActionStarted::class, PackageActionSucceeded::class, PackageActionFailed::class]);

        $result = $this->reporter()->track(PackageActionType::Custom, 'unit', null, fn (): string => 'ok');

        $this->assertSame('ok', $result);
        Event::assertDispatched(PackageActionStarted::class);
        Event::assertDispatched(PackageActionSucceeded::class);
        Event::assertNotDispatched(PackageActionFailed::class);
    }

    public function test_track_emits_start_then_failure_and_rethrows(): void
    {
        Event::fake([PackageActionStarted::class, PackageActionSucceeded::class, PackageActionFailed::class]);

        try {
            $this->reporter()->track(PackageActionType::Custom, 'unit', null, function (): never {
                throw new RuntimeException('nope');
            });
            $this->fail('track() should rethrow the work exception');
        } catch (RuntimeException $e) {
            $this->assertSame('nope', $e->getMessage());
        }

        Event::assertDispatched(PackageActionStarted::class);
        Event::assertDispatched(PackageActionFailed::class);
        Event::assertNotDispatched(PackageActionSucceeded::class);
    }
}
