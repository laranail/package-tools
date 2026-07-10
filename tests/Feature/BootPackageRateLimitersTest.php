<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature;

use Closure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\Package\Tools\Support\Definitions\RateLimiterDefinition;

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

    public function test_a_rate_limiter_definition_resolves_through_the_boot_chain(): void
    {
        $limiter = RateLimiter::limiter('pkg-login');
        $this->assertInstanceOf(Closure::class, $limiter);

        $limit = $limiter(Request::create('/login', 'POST', ['email' => 'A@B.com'], [], [], ['REMOTE_ADDR' => '198.51.100.9']));

        $this->assertInstanceOf(Limit::class, $limit);
        $this->assertSame(7, $limit->maxAttempts);
        $this->assertSame('a@b.com|198.51.100.9', $limit->key);
    }
}

final class RateLimiterTestPackageProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->setName('test/rate-limiters');
        $package->basePath = sys_get_temp_dir();

        $package->registerRateLimiter('pkg-api', static fn (): Limit => Limit::perMinute(5));

        $package->registerRateLimiter(
            RateLimiterDefinition::make('pkg-login')
                ->perMinute(fn (): int => 7)
                ->byField('email'),
        );
    }
}
