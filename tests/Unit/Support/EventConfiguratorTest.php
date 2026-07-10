<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Support;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Event;
use Orchestra\Testbench\TestCase;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Exceptions\PackageBootException;
use Simtabi\Laranail\Package\Tools\Package;

/**
 * EventConfigurator: the fluent event() sub-builder. It adds only the
 * pair-form normalization and the closure-subscriber path over the package's
 * existing event storage.
 */
final class EventConfiguratorTest extends TestCase
{
    public function test_add_listeners_accepts_both_the_pair_and_map_shapes(): void
    {
        $package = (new Package)->name('acme/x');

        $package->event()->addListeners([
            ['acme.login', 'AcmeLoginListener'],            // pair form
            ['acme.login', 'AcmeAuditListener'],
        ])->addListeners([
            'acme.logout' => 'AcmeLogoutListener',          // map form (single)
            'acme.failed' => ['AcmeFailedA', 'AcmeFailedB'], // map form (array)
        ]);

        $listeners = $package->getEventListeners();

        $this->assertSame(['AcmeLoginListener', 'AcmeAuditListener'], $listeners['acme.login']);
        $this->assertSame(['AcmeLogoutListener'], $listeners['acme.logout']);
        $this->assertSame(['AcmeFailedA', 'AcmeFailedB'], $listeners['acme.failed']);
    }

    public function test_add_subscriber_routes_class_strings_and_closures_separately(): void
    {
        $package = (new Package)->name('acme/x');

        $package->event()
            ->addSubscriber('AcmeClassSubscriber')
            ->addSubscriber(static fn (Dispatcher $events) => $events->listen('acme.ping', static fn (): null => null));

        $this->assertContains('AcmeClassSubscriber', $package->getEventSubscribers());
        $this->assertCount(1, $package->getEventSubscriberCallbacks());
    }

    public function test_closure_subscribers_run_against_the_dispatcher_at_boot(): void
    {
        $package = (new Package)->name('acme/x');

        $package->event()->addSubscriber(
            static fn (Dispatcher $events) => $events->listen('acme.ping', static fn (): string => 'pong'),
        );

        $package->bootPackageEventSubscriberCallbacks($this->app->make(Dispatcher::class));

        $responses = Event::dispatch('acme.ping');

        $this->assertSame(['pong'], $responses);
    }

    public function test_a_throwing_subscriber_closure_fails_loud_with_an_annotated_exception(): void
    {
        $package = (new Package)->name('acme/x');
        $package->event()->addSubscriber(static function (Dispatcher $events): never {
            throw new RuntimeException('subscriber wiring failed');
        });

        try {
            $package->bootPackageEventSubscriberCallbacks($this->app->make(Dispatcher::class));
            $this->fail('a throwing subscriber closure must fail loud');
        } catch (PackageBootException $e) {
            $this->assertStringContainsString('[event subscriber]', $e->getMessage());
            $this->assertStringContainsString('subscriber wiring failed', $e->getMessage());
        }
    }
}
