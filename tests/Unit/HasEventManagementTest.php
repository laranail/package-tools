<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Package\Tools\Concerns\Package\HasEventManagement;
use Simtabi\Laranail\Package\Tools\Tests\Fixtures\Events\UserRegistered;
use Simtabi\Laranail\Package\Tools\Tests\Fixtures\Listeners\InvokableListener;
use Simtabi\Laranail\Package\Tools\Tests\Fixtures\Listeners\SendWelcome;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

/**
 * HasEventManagementTest - Test event listener & subscriber management.
 */
class HasEventManagementTest extends TestCase
{
    use HasEventManagement;

    #[Test]
    public function it_registers_event_listener(): void
    {
        $this->registerEventListener('App\Events\UserCreated', 'App\Listeners\SendWelcome');

        $listeners = $this->getEventListeners();

        $this->assertArrayHasKey('App\Events\UserCreated', $listeners);
        $this->assertContains('App\Listeners\SendWelcome', $listeners['App\Events\UserCreated']);
    }

    #[Test]
    public function it_registers_multiple_listeners_for_same_event(): void
    {
        $this->registerEventListener('UserCreated', 'SendWelcome');
        $this->registerEventListener('UserCreated', 'LogCreation');

        $listeners = $this->getEventListeners();

        $this->assertCount(2, $listeners['UserCreated']);
    }

    #[Test]
    public function it_allows_same_listener_for_multiple_events(): void
    {
        $this->registerEventListener('Event1', 'SharedListener');
        $this->registerEventListener('Event2', 'SharedListener');

        $listeners = $this->getEventListeners();

        $this->assertCount(2, $listeners);
        $this->assertContains('SharedListener', $listeners['Event1']);
        $this->assertContains('SharedListener', $listeners['Event2']);
    }

    #[Test]
    public function it_registers_listeners_in_bulk_with_single_listener_shape(): void
    {
        $this->registerEventListeners([
            'EventA' => 'ListenerA',
            'EventB' => 'ListenerB',
        ]);

        $listeners = $this->getEventListeners();

        $this->assertContains('ListenerA', $listeners['EventA']);
        $this->assertContains('ListenerB', $listeners['EventB']);
    }

    #[Test]
    public function it_registers_listeners_in_bulk_with_array_listener_shape(): void
    {
        $this->registerEventListeners([
            'EventA' => ['ListenerA1', 'ListenerA2'],
        ]);

        $listeners = $this->getEventListeners();

        $this->assertCount(2, $listeners['EventA']);
        $this->assertContains('ListenerA1', $listeners['EventA']);
        $this->assertContains('ListenerA2', $listeners['EventA']);
    }

    #[Test]
    public function it_registers_event_subscriber(): void
    {
        $this->registerEventSubscriber('App\Subscribers\BlogSubscriber');

        $subscribers = $this->getEventSubscribers();

        $this->assertCount(1, $subscribers);
        $this->assertContains('App\Subscribers\BlogSubscriber', $subscribers);
    }

    #[Test]
    public function it_registers_subscribers_in_bulk(): void
    {
        $this->registerEventSubscribers(['BlogSubscriber', 'UserSubscriber']);

        $subscribers = $this->getEventSubscribers();

        $this->assertCount(2, $subscribers);
        $this->assertContains('BlogSubscriber', $subscribers);
        $this->assertContains('UserSubscriber', $subscribers);
    }

    #[Test]
    public function it_returns_empty_array_when_no_events_registered(): void
    {
        $this->assertEmpty($this->getEventListeners());
        $this->assertEmpty($this->getEventSubscribers());
    }

    #[Test]
    public function it_chains_event_registrations(): void
    {
        $result = $this->registerEventListener('UserCreated', 'SendWelcome')
            ->registerEventSubscriber('BlogSubscriber');

        $this->assertInstanceOf(static::class, $result);
        $this->assertCount(1, $this->getEventListeners());
        $this->assertCount(1, $this->getEventSubscribers());
    }

    #[Test]
    public function it_discovers_event_listeners_from_directory(): void
    {
        $this->discoverEventListeners(
            'Fixtures/Listeners',
            'Simtabi\\Laranail\\Package\\Tools\\Tests\\Fixtures\\Listeners',
        );

        $listeners = $this->getEventListeners();

        $this->assertArrayHasKey(UserRegistered::class, $listeners);
        $this->assertContains(SendWelcome::class, $listeners[UserRegistered::class]);
        $this->assertContains(InvokableListener::class, $listeners[UserRegistered::class]);
        $this->assertCount(2, $listeners[UserRegistered::class]);
    }

    /**
     * Point package base path at the tests/ directory so
     * packageBasePath('Fixtures/Listeners') resolves to the fixtures dir.
     */
    protected function packageBasePath(string $path = ''): string
    {
        $base = dirname(__DIR__);

        return $path === '' ? $base : $base . '/' . ltrim($path, '/');
    }
}
