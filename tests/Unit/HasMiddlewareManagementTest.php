<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Package\Tools\Concerns\Package\HasMiddlewareManagement;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

/**
 * HasMiddlewareManagementTest - Test middleware management
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
    public function it_returns_empty_array_when_no_middleware_registered(): void
    {
        $this->assertEmpty($this->getRouteMiddleware());
        $this->assertEmpty($this->getGlobalMiddleware());
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
    public function it_supports_fluent_api(): void
    {
        $this->registerRouteMiddleware('auth', 'Auth')
            ->registerGlobalMiddleware('Global');

        $this->assertCount(1, $this->getRouteMiddleware());
        $this->assertCount(1, $this->getGlobalMiddleware());
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
}
