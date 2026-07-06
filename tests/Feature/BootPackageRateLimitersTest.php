<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature;

use Closure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;

/**
 * registerRateLimiter() on the Package must reach RateLimiter::for()
 * through bootPackageDeferredHooks() during the provider's boot chain.
 */
final class BootPackageRateLimitersTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [RateLimiterTestPackageProvider::class];
    }

    public function test_registered_limiter_is_available_by_name(): void
    {
        $limiter = RateLimiter::limiter('pkg-api');

        $this->assertInstanceOf(Closure::class, $limiter);
    }

    public function test_registered_limiter_closure_produces_the_configured_limit(): void
    {
        $limiter = RateLimiter::limiter('pkg-api');

        $limit = $limiter();

        $this->assertInstanceOf(Limit::class, $limit);
        $this->assertSame(5, $limit->maxAttempts);
    }
}

final class RateLimiterTestPackageProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->setName('test/rate-limiters');
        $package->basePath = sys_get_temp_dir();

        $package->registerRateLimiter('pkg-api', static fn (): Limit => Limit::perMinute(5));
    }
}
