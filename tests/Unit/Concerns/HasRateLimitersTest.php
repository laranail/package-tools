<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Concerns;

use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Package\Tools\Concerns\Package\HasRateLimiters;
use Simtabi\Laranail\Package\Tools\Support\Definitions\RateLimiterDefinition;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

/**
 * declarative named rate limiters: storage shapes and the boot wiring
 * into the RateLimiter facade.
 */
final class HasRateLimitersTest extends TestCase
{
    use HasRateLimiters;

    #[Test]
    public function it_registers_a_single_limiter(): void
    {
        $limiter = static fn (): int => 60;

        $this->registerRateLimiter('api', $limiter);

        $this->assertSame(['api' => $limiter], $this->getRateLimiters());
    }

    #[Test]
    public function it_registers_a_map_of_limiters(): void
    {
        $api = static fn (): int => 60;
        $uploads = static fn (): int => 10;

        $this->registerRateLimiters(['api' => $api, 'uploads' => $uploads]);

        $this->assertSame(['api' => $api, 'uploads' => $uploads], $this->getRateLimiters());
    }

    #[Test]
    public function a_later_registration_replaces_the_named_entry(): void
    {
        $first = static fn (): int => 60;
        $second = static fn (): int => 120;

        $this->registerRateLimiter('api', $first);
        $this->registerRateLimiter('api', $second);

        $this->assertSame(['api' => $second], $this->getRateLimiters());
    }

    #[Test]
    public function registration_is_fluent(): void
    {
        $result = $this->registerRateLimiter('api', static fn (): int => 60)
            ->registerRateLimiters(['uploads' => static fn (): int => 10]);

        $this->assertSame($this, $result);
        $this->assertCount(2, $this->getRateLimiters());
    }

    #[Test]
    public function boot_registers_the_limiters_with_the_facade(): void
    {
        $limiter = static fn (): int => 60;

        $this->registerRateLimiter('package-api', $limiter);
        $this->bootPackageRateLimiters();

        // the facade wraps registered limiters, so assert behavior: the
        // named limiter resolves and delegates to our closure
        $resolved = RateLimiter::limiter('package-api');

        $this->assertNotNull($resolved);
        $this->assertSame(60, $resolved());
    }

    #[Test]
    public function it_registers_a_definition_under_its_own_name(): void
    {
        $definition = RateLimiterDefinition::make('login')->perMinute(5)->byIp();

        $this->registerRateLimiter($definition);

        $this->assertSame(['login' => $definition], $this->getRateLimiters());
    }

    #[Test]
    public function a_string_name_without_a_closure_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // a bare name with no closure is a runtime misuse the guard rejects
        $this->registerRateLimiter('api');
    }
}
