<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature;

use Closure;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Orchestra\Testbench\TestCase;
use Override;
use ReflectionClass;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;

/**
 * Locks in the regression fix for the audit-identified gap:
 * `bootPackageDeferredHooks()` must wire middleware/event/factory/seeder
 * registration on the Package object during the provider's boot chain.
 *
 * If the chain is unhooked again, every assertion below breaks.
 */
final class BootPackageDeferredHooksTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [TestPackageProvider::class];
    }

    public function test_middleware_aliases_register_at_boot(): void
    {
        $router = $this->app->make(Router::class);
        $aliases = $router->getMiddleware();

        $this->assertArrayHasKey('test.alias', $aliases);
        $this->assertSame(StubMiddleware::class, $aliases['test.alias']);
    }

    public function test_global_middleware_pushes_at_boot(): void
    {
        $kernel = $this->app->make(HttpKernel::class);

        $this->assertTrue(in_array(
            StubGlobalMiddleware::class,
            $this->extractGlobalMiddleware($kernel),
            true,
        ));
    }

    public function test_event_listener_registers_at_boot(): void
    {
        $this->assertTrue(Event::hasListeners('package-tools.test.event'));
    }

    public function test_event_subscriber_registers_at_boot(): void
    {
        $this->assertTrue(Event::hasListeners('package-tools.subscribed.event'));
    }

    public function test_factory_paths_register_at_boot(): void
    {
        $package = $this->app->make(TestPackageProvider::class)->package;

        $this->assertContains('database/factories/Test', $package->getFactoryPaths());
    }

    public function test_seeder_paths_register_at_boot(): void
    {
        $package = $this->app->make(TestPackageProvider::class)->package;

        $this->assertContains('database/seeders/Test', $package->getSeederPaths());
    }

    /** @return list<string> */
    private function extractGlobalMiddleware(HttpKernel $kernel): array
    {
        $reflection = new ReflectionClass($kernel);
        if (! $reflection->hasProperty('middleware')) {
            return [];
        }
        $prop = $reflection->getProperty('middleware');

        return (array) $prop->getValue($kernel);
    }
}

final class StubMiddleware
{
    public function handle($request, Closure $next)
    {
        return $next($request);
    }
}

final class StubGlobalMiddleware
{
    public function handle($request, Closure $next)
    {
        return $next($request);
    }
}

final class StubListener {}

final class StubSubscriber
{
    public function subscribe($events): void
    {
        $events->listen('package-tools.subscribed.event', static fn (): null => null);
    }
}

final class TestPackageProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->setName('test/deferred-hooks');
        $package->basePath = sys_get_temp_dir();

        $package->registerRouteMiddleware('test.alias', StubMiddleware::class);
        $package->registerGlobalMiddleware(StubGlobalMiddleware::class);
        $package->registerEventListener('package-tools.test.event', StubListener::class);
        $package->registerEventSubscriber(StubSubscriber::class);
        $package->loadFactoriesFrom('database/factories/Test');
        $package->loadSeedersFrom('database/seeders/Test');
    }

    #[Override]
    public function register(): void
    {
        parent::register();

        $this->app->singleton(self::class, fn (): static => $this);
    }
}
