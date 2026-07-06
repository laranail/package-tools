<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Concerns;

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Routing\Router;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\Package\Tools\Package;

/**
 * the enhanced middleware vocabulary over the deferred registry: batch
 * alias registration, group storage, and prefixed aliases — everything
 * stores on the package for bootPackageMiddleware() to apply later.
 */
class HasEnhancedMiddlewareTest extends TestCase
{
    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();
        $this->package = new Package;
        $this->package->setName('test-vendor/test-package');
    }

    #[Test]
    public function register_route_middlewares_stores_a_batch_of_aliases(): void
    {
        $result = $this->package->registerRouteMiddlewares([
            'auth.blog' => 'App\\Http\\Middleware\\BlogAuth',
            'admin.blog' => 'App\\Http\\Middleware\\BlogAdmin',
        ]);

        $this->assertSame($this->package, $result, 'Should support fluent chaining');
        $this->assertSame([
            'auth.blog' => 'App\\Http\\Middleware\\BlogAuth',
            'admin.blog' => 'App\\Http\\Middleware\\BlogAdmin',
        ], $this->package->getRouteMiddleware());
    }

    #[Test]
    public function register_middleware_alias_is_deferred_alias_registration(): void
    {
        $this->package->registerMiddlewareAlias('auth.blog', 'App\\Http\\Middleware\\BlogAuth');

        $this->assertSame(
            ['auth.blog' => 'App\\Http\\Middleware\\BlogAuth'],
            $this->package->getRouteMiddleware(),
        );
    }

    #[Test]
    public function register_middleware_aliases_batches_through_the_same_registry(): void
    {
        $this->package->registerMiddlewareAliases([
            'a' => 'App\\A',
            'b' => 'App\\B',
        ]);

        $this->assertCount(2, $this->package->getRouteMiddleware());
    }

    #[Test]
    public function register_middleware_group_stores_the_group_deferred(): void
    {
        $result = $this->package->registerMiddlewareGroup('blog', ['App\\A', 'App\\B']);

        $this->assertSame($this->package, $result, 'Should support fluent chaining');
        $this->assertSame(['blog' => ['App\\A', 'App\\B']], $this->package->getMiddlewareGroups());
    }

    #[Test]
    public function register_middleware_group_reindexes_the_middleware_list(): void
    {
        $this->package->registerMiddlewareGroup('blog', [5 => 'App\\A', 9 => 'App\\B']);

        $this->assertSame(['App\\A', 'App\\B'], $this->package->getMiddlewareGroups()['blog']);
    }

    #[Test]
    public function register_middleware_groups_stores_multiple_groups(): void
    {
        $this->package->registerMiddlewareGroups([
            'blog' => ['App\\A'],
            'admin' => ['App\\B', 'App\\C'],
        ]);

        $this->assertSame([
            'blog' => ['App\\A'],
            'admin' => ['App\\B', 'App\\C'],
        ], $this->package->getMiddlewareGroups());
    }

    #[Test]
    public function add_to_middleware_group_appends_to_an_existing_group(): void
    {
        $this->package->registerMiddlewareGroup('blog', ['App\\A']);
        $this->package->addToMiddlewareGroup('blog', 'App\\B');

        $this->assertSame(['blog' => ['App\\A', 'App\\B']], $this->package->getMiddlewareGroups());
    }

    #[Test]
    public function add_to_middleware_group_creates_a_missing_group(): void
    {
        $this->package->addToMiddlewareGroup('blog', 'App\\A');

        $this->assertSame(['blog' => ['App\\A']], $this->package->getMiddlewareGroups());
    }

    #[Test]
    public function registering_a_group_again_replaces_it(): void
    {
        $this->package->registerMiddlewareGroup('blog', ['App\\A']);
        $this->package->registerMiddlewareGroup('blog', ['App\\B']);

        $this->assertSame(['blog' => ['App\\B']], $this->package->getMiddlewareGroups());
    }

    #[Test]
    public function boot_defines_a_group_the_router_does_not_have(): void
    {
        $router = $this->makeRouter();

        $this->package->registerMiddlewareGroup('blog', ['App\\A', 'App\\B']);
        $this->package->bootPackageMiddleware($router);

        $this->assertSame(['App\\A', 'App\\B'], $router->getMiddlewareGroups()['blog']);
    }

    #[Test]
    public function boot_appends_to_a_group_the_host_already_defined(): void
    {
        $router = $this->makeRouter();
        $router->middlewareGroup('web', ['App\\HostSession', 'App\\HostCsrf']);

        // addToMiddlewareGroup('web', …) must append to the host's web
        // group, never replace it with the package's short list
        $this->package->addToMiddlewareGroup('web', 'App\\PackageMiddleware');
        $this->package->bootPackageMiddleware($router);

        $this->assertSame(
            ['App\\HostSession', 'App\\HostCsrf', 'App\\PackageMiddleware'],
            $router->getMiddlewareGroups()['web'],
        );
    }

    #[Test]
    public function register_prefixed_middleware_defaults_to_the_package_short_name(): void
    {
        $this->package->registerPrefixedMiddleware(['auth' => 'App\\Auth']);

        $this->assertSame(
            ['test-package.auth' => 'App\\Auth'],
            $this->package->getRouteMiddleware(),
        );
    }

    #[Test]
    public function register_prefixed_middleware_honours_an_explicit_prefix(): void
    {
        $this->package->registerPrefixedMiddleware(['auth' => 'App\\Auth'], 'blog');

        $this->assertSame(
            ['blog.auth' => 'App\\Auth'],
            $this->package->getRouteMiddleware(),
        );
    }

    private function makeRouter(): Router
    {
        return new Router(new Dispatcher, new Container);
    }
}
