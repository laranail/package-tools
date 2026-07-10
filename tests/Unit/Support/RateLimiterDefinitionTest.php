<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Cache\RateLimiting\Unlimited;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use InvalidArgumentException;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\Package\Tools\Support\Definitions\RateLimiterDefinition;

/**
 * The fluent RateLimiterDefinition: attempt resolution, key shortcuts,
 * multi-limit composition, and the escape hatches.
 */
final class RateLimiterDefinitionTest extends TestCase
{
    private function request(array $input = [], string $ip = '203.0.113.7'): Request
    {
        return Request::create('/login', 'POST', $input, [], [], ['REMOTE_ADDR' => $ip]);
    }

    public function test_fixed_attempts_and_by_field_build_the_expected_limit(): void
    {
        $limit = RateLimiterDefinition::make('login')
            ->perMinute(5)
            ->byField('email')
            ->resolve($this->request(['email' => 'USER@Example.com']));

        $this->assertInstanceOf(Limit::class, $limit);
        $this->assertSame(5, $limit->maxAttempts);
        $this->assertSame('user@example.com|203.0.113.7', $limit->key);
    }

    public function test_dynamic_attempts_closure_is_resolved(): void
    {
        $attempts = 9;

        $limit = RateLimiterDefinition::make('login')
            ->perMinute(fn (): int => max($attempts, 1))
            ->byIp()
            ->resolve($this->request());

        $this->assertSame(9, $limit->maxAttempts);
        $this->assertSame('203.0.113.7', $limit->key);
    }

    public function test_by_field_without_ip(): void
    {
        $limit = RateLimiterDefinition::make('x')
            ->perMinute(3)
            ->byField('email', withIp: false)
            ->resolve($this->request(['email' => 'A@B.com']));

        $this->assertSame('a@b.com', $limit->key);
    }

    public function test_by_session_key_is_guarded_and_reads_the_session(): void
    {
        $withoutSession = RateLimiterDefinition::make('two-factor')
            ->perMinute(5)
            ->bySessionKey('login.id')
            ->resolve($this->request());

        $this->assertSame('', $withoutSession->key);

        $request = $this->request();
        $session = new Store('test', new ArraySessionHandler(60));
        $session->put('login.id', 42);
        $request->setLaravelSession($session);

        $withSession = RateLimiterDefinition::make('two-factor')
            ->perMinute(5)
            ->bySessionKey('login.id')
            ->resolve($request);

        $this->assertSame('42', $withSession->key);
    }

    public function test_multiple_windows_compose_into_an_array_of_limits(): void
    {
        $limits = RateLimiterDefinition::make('api')
            ->perMinute(60)->byIp()
            ->perDay(10_000)->byIp()
            ->resolve($this->request());

        $this->assertIsArray($limits);
        $this->assertCount(2, $limits);
        $this->assertSame(60, $limits[0]->maxAttempts);
        $this->assertSame(60, $limits[0]->decaySeconds);
        $this->assertSame(10_000, $limits[1]->maxAttempts);
        $this->assertSame(86_400, $limits[1]->decaySeconds);
    }

    public function test_unlimited_produces_a_none_limit(): void
    {
        $limit = RateLimiterDefinition::make('x')->unlimited()->resolve($this->request());

        $this->assertInstanceOf(Unlimited::class, $limit);
    }

    public function test_response_callback_is_attached(): void
    {
        $callback = static fn (): string => 'nope';

        $limit = RateLimiterDefinition::make('x')
            ->perMinute(1)
            ->byIp()
            ->response($callback)
            ->resolve($this->request());

        $this->assertSame($callback, $limit->responseCallback);
    }

    public function test_using_bypasses_the_specs(): void
    {
        $limit = RateLimiterDefinition::make('x')
            ->perMinute(1) // ignored
            ->using(static fn (Request $request): Limit => Limit::perHour(7)->by('custom'))
            ->resolve($this->request());

        $this->assertSame(7, $limit->maxAttempts);
        $this->assertSame('custom', $limit->key);
    }

    public function test_an_unconfigured_definition_is_unlimited(): void
    {
        $limit = RateLimiterDefinition::make('x')->resolve($this->request());

        $this->assertInstanceOf(Unlimited::class, $limit);
    }

    public function test_a_key_method_before_a_window_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RateLimiterDefinition::make('x')->byIp();
    }
}
