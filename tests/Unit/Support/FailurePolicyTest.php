<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Support;

use Illuminate\Support\Facades\Log;
use Orchestra\Testbench\TestCase;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Support\Resilience\FailurePolicy;

/**
 * The package-wide resilience policy: strict in dev (rethrow), lenient in
 * prod (log + skip), always logging, overridable via resilience.strict.
 */
final class FailurePolicyTest extends TestCase
{
    public function test_it_is_strict_outside_production_by_default(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');

        $this->assertTrue(FailurePolicy::isStrict());
    }

    public function test_it_is_lenient_in_production_by_default(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->assertFalse(FailurePolicy::isStrict());
    }

    public function test_the_config_flag_overrides_the_environment(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config()->set('package-tools.resilience.strict', true);
        $this->assertTrue(FailurePolicy::isStrict());

        $this->app->detectEnvironment(fn (): string => 'local');
        config()->set('package-tools.resilience.strict', false);
        $this->assertFalse(FailurePolicy::isStrict());
    }

    public function test_guard_rethrows_and_logs_when_strict(): void
    {
        config()->set('package-tools.resilience.strict', true);
        Log::spy();

        try {
            FailurePolicy::guard(static function (): never {
                throw new RuntimeException('boom');
            }, 'Test');
            $this->fail('strict guard should rethrow');
        } catch (RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        Log::shouldHaveReceived('error')->once();
    }

    public function test_guard_swallows_and_logs_and_returns_default_when_lenient(): void
    {
        config()->set('package-tools.resilience.strict', false);
        Log::spy();

        $result = FailurePolicy::guard(static function (): never {
            throw new RuntimeException('boom');
        }, 'Test', default: 'fallback');

        $this->assertSame('fallback', $result);
        Log::shouldHaveReceived('error')->once();
    }

    public function test_guard_returns_the_work_result_on_success(): void
    {
        $this->assertSame(42, FailurePolicy::guard(static fn (): int => 42, 'Test'));
    }
}
