<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature;

use Closure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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

    public function test_booted_event_listener_fires_on_dispatch(): void
    {
        $this->assertTrue(Event::hasListeners('package-tools.test.event'));

        StubListener::$fired = false;

        // Dispatching resolves through the listener pipeline and actually runs
        // the registered listener's handle().
        $results = Event::dispatch('package-tools.test.event');

        $this->assertIsArray($results);
        $this->assertTrue(StubListener::$fired, 'The booted listener should fire on dispatch.');
        $this->assertContains('handled', $results);
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

    public function test_policy_registers_at_boot(): void
    {
        // one provider wiring a policy alongside the other hooks proves the
        // 2.0 additions ride the same deferred-hooks chain
        $this->assertInstanceOf(
            StubDeferredPolicy::class,
            Gate::getPolicyFor(StubDeferredModel::class),
        );
    }

    public function test_morph_map_registers_at_boot(): void
    {
        $this->assertSame(
            StubDeferredModel::class,
            Relation::getMorphedModel('deferred-thing'),
        );
    }

    public function test_rate_limiter_registers_at_boot(): void
    {
        $limiter = RateLimiter::limiter('deferred-limiter');

        $this->assertInstanceOf(Closure::class, $limiter);
        $this->assertSame(3, $limiter()->maxAttempts);
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

final class StubListener
{
    public static bool $fired = false;

    public function handle(): string
    {
        self::$fired = true;

        return 'handled';
    }
}

final class StubSubscriber
{
    public function subscribe($events): void
    {
        $events->listen('package-tools.subscribed.event', static fn (): null => null);
    }
}

final class StubDeferredModel extends Model {}

final class StubDeferredPolicy
{
    public function view(): bool
    {
        return true;
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

        // 2.0 deferred hooks: one provider must boot all of these together
        $package->registerPolicy(StubDeferredModel::class, StubDeferredPolicy::class);
        $package->registerMorphMap(['deferred-thing' => StubDeferredModel::class]);
        $package->registerRateLimiter('deferred-limiter', static fn (): Limit => Limit::perMinute(3));
    }

    #[Override]
    public function register(): void
    {
        parent::register();

        $this->app->singleton(self::class, fn (): static => $this);
    }
}
