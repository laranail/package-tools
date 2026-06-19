<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\PackageTools\Concerns\Package\HasMiddlewareManagement;
use Simtabi\Laranail\PackageTools\Tests\TestCase;

/**
 * HasMiddlewareManagementTest - Test middleware & event management
 *
 * Covers all 13 Phase 5 features
 */
class HasMiddlewareManagementTest extends TestCase
{
    use HasMiddlewareManagement;

    #[Test]
    public function it_registers_route_middleware(): void
    {
        $this->registerRouteMiddleware('auth.blog', 'App\Http\Middleware\BlogAuth');

        $middleware = $this->getRouteMiddleware();

        $this->assertCount(1, $middleware);
        $this->assertEquals('App\Http\Middleware\BlogAuth', $middleware['auth.blog']);
    }

    #[Test]
    public function it_registers_multiple_route_middleware(): void
    {
        $this->registerRouteMiddleware('auth.blog', 'BlogAuth');
        $this->registerRouteMiddleware('admin.blog', 'BlogAdmin');

        $middleware = $this->getRouteMiddleware();

        $this->assertCount(2, $middleware);
    }

    #[Test]
    public function it_registers_global_middleware(): void
    {
        $this->registerGlobalMiddleware('App\Http\Middleware\GlobalBlog');

        $middleware = $this->getGlobalMiddleware();

        $this->assertCount(1, $middleware);
        $this->assertContains('App\Http\Middleware\GlobalBlog', $middleware);
    }

    #[Test]
    public function it_registers_multiple_global_middleware(): void
    {
        $this->registerGlobalMiddleware('GlobalBlog1');
        $this->registerGlobalMiddleware('GlobalBlog2');

        $middleware = $this->getGlobalMiddleware();

        $this->assertCount(2, $middleware);
    }

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
    public function it_registers_event_subscriber(): void
    {
        $this->registerEventSubscriber('App\Subscribers\BlogSubscriber');

        $subscribers = $this->getEventSubscribers();

        $this->assertCount(1, $subscribers);
        $this->assertContains('App\Subscribers\BlogSubscriber', $subscribers);
    }

    #[Test]
    public function it_registers_multiple_event_subscribers(): void
    {
        $this->registerEventSubscriber('BlogSubscriber');
        $this->registerEventSubscriber('UserSubscriber');

        $subscribers = $this->getEventSubscribers();

        $this->assertCount(2, $subscribers);
    }

    #[Test]
    public function it_returns_empty_array_when_no_middleware_registered(): void
    {
        $this->assertEmpty($this->getRouteMiddleware());
        $this->assertEmpty($this->getGlobalMiddleware());
    }

    #[Test]
    public function it_returns_empty_array_when_no_events_registered(): void
    {
        $this->assertEmpty($this->getEventListeners());
        $this->assertEmpty($this->getEventSubscribers());
    }

    #[Test]
    public function it_chains_middleware_registrations(): void
    {
        $result = $this->registerRouteMiddleware('auth', 'AuthMiddleware')
            ->registerRouteMiddleware('admin', 'AdminMiddleware');

        $this->assertInstanceOf(static::class, $result);
        $this->assertCount(2, $this->getRouteMiddleware());
    }

    #[Test]
    public function it_chains_event_registrations(): void
    {
        $result = $this->registerEventListener('UserCreated', 'SendWelcome')
            ->registerEventSubscriber('BlogSubscriber');

        $this->assertInstanceOf(static::class, $result);
    }

    #[Test]
    public function it_supports_fluent_api(): void
    {
        $this->registerRouteMiddleware('auth', 'Auth')
            ->registerGlobalMiddleware('Global')
            ->registerEventListener('Event', 'Listener')
            ->registerEventSubscriber('Subscriber');

        $this->assertCount(1, $this->getRouteMiddleware());
        $this->assertCount(1, $this->getGlobalMiddleware());
        $this->assertCount(1, $this->getEventListeners());
        $this->assertCount(1, $this->getEventSubscribers());
    }

    #[Test]
    public function it_maintains_registration_order(): void
    {
        $this->registerGlobalMiddleware('First');
        $this->registerGlobalMiddleware('Second');
        $this->registerGlobalMiddleware('Third');

        $middleware = $this->getGlobalMiddleware();

        $this->assertEquals('First', $middleware[0]);
        $this->assertEquals('Second', $middleware[1]);
        $this->assertEquals('Third', $middleware[2]);
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
}
