<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Services\Event;

use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\Package\Tools\Services\Event\EventRegistry;

final class FakeSubscriber
{
    public function subscribe(): void
    {
        // No-op: registration only needs the class to be resolvable.
    }
}

final class EventRegistryTest extends TestCase
{
    private EventRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new EventRegistry;
    }

    public function test_register_listener_tracks_per_event(): void
    {
        $this->registry->registerListener('user.created', 'App\\Listeners\\SendWelcome');
        $this->registry->registerListener('user.created', 'App\\Listeners\\Notify');

        $this->assertSame(
            ['App\\Listeners\\SendWelcome', 'App\\Listeners\\Notify'],
            $this->registry->getListeners('user.created'),
        );
        $this->assertTrue($this->registry->hasListener('user.created'));
    }

    public function test_get_listeners_empty_for_unknown_event(): void
    {
        $this->assertSame([], $this->registry->getListeners('nothing'));
        $this->assertFalse($this->registry->hasListener('nothing'));
    }

    public function test_register_with_array_value_adds_each_listener(): void
    {
        $this->registry->register('order.placed', ['ListenerA', 'ListenerB']);

        $this->assertSame(['ListenerA', 'ListenerB'], $this->registry->getListeners('order.placed'));
    }

    public function test_register_with_scalar_value_adds_single_listener(): void
    {
        $this->registry->register('order.placed', 'SoleListener');

        $this->assertSame(['SoleListener'], $this->registry->getListeners('order.placed'));
    }

    public function test_register_subscriber_tracked(): void
    {
        $this->registry->registerSubscriber(FakeSubscriber::class);

        $registered = $this->registry->getRegistered();
        $this->assertContains(FakeSubscriber::class, $registered['subscribers']);
        $this->assertTrue($this->registry->has(FakeSubscriber::class));
    }

    public function test_has_checks_listeners_and_subscribers(): void
    {
        $this->registry->registerListener('e1', 'L1');
        $this->registry->registerSubscriber(FakeSubscriber::class);

        $this->assertTrue($this->registry->has('e1'));
        $this->assertTrue($this->registry->has(FakeSubscriber::class));
        $this->assertFalse($this->registry->has('unknown'));
    }

    public function test_get_returns_listeners_or_default(): void
    {
        $this->registry->registerListener('e1', 'L1');

        $this->assertSame(['L1'], $this->registry->get('e1'));
        $this->assertSame('fallback', $this->registry->get('missing', 'fallback'));
    }

    public function test_get_registered_returns_both_collections(): void
    {
        $this->registry->registerListener('e1', 'L1');
        $this->registry->registerSubscriber(FakeSubscriber::class);

        $registered = $this->registry->getRegistered();

        $this->assertSame(['e1' => ['L1']], $registered['listeners']);
        $this->assertSame([FakeSubscriber::class], $registered['subscribers']);
    }

    public function test_unregister_removes_listener_and_subscriber(): void
    {
        $this->registry->registerListener('e1', 'L1');
        $this->registry->registerSubscriber(FakeSubscriber::class);

        $this->registry->unregister('e1');
        $this->registry->unregister(FakeSubscriber::class);

        $this->assertFalse($this->registry->has('e1'));
        $this->assertFalse($this->registry->has(FakeSubscriber::class));
    }
}
