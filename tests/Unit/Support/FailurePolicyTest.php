<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Support;

use Illuminate\Support\Facades\Log;
use Orchestra\Testbench\TestCase;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Exceptions\PackageBootException;
use Simtabi\Laranail\Package\Tools\Support\Resilience\FailurePolicy;

/**
 * Boot-wiring failure handling: rethrowing() fails loud with an annotated
 * exception; swallow() degrades safely (logs + skips).
 */
final class FailurePolicyTest extends TestCase
{
    public function test_rethrowing_returns_the_result_on_success(): void
    {
        $this->assertSame(42, FailurePolicy::rethrowing(static fn (): int => 42, 'thing'));
    }

    public function test_rethrowing_wraps_and_rethrows_naming_the_subject(): void
    {
        try {
            FailurePolicy::rethrowing(static function (): never {
                throw new RuntimeException('scheme is bad');
            }, 'useHttps');
            $this->fail('rethrowing() must rethrow');
        } catch (PackageBootException $e) {
            $this->assertStringContainsString('[useHttps]', $e->getMessage());
            $this->assertStringContainsString('scheme is bad', $e->getMessage());
            $this->assertInstanceOf(RuntimeException::class, $e->getPrevious());
        }
    }

    public function test_rethrowing_does_not_double_wrap_a_boot_exception(): void
    {
        $original = PackageBootException::from('inner', new RuntimeException('x'));

        try {
            FailurePolicy::rethrowing(static function () use ($original): never {
                throw $original;
            }, 'outer');
            $this->fail('should rethrow');
        } catch (PackageBootException $e) {
            $this->assertSame($original, $e);
        }
    }

    public function test_swallow_logs_skips_and_returns_the_default(): void
    {
        Log::spy();

        $result = FailurePolicy::swallow(static function (): never {
            throw new RuntimeException('boom');
        }, 'Config', default: 'fallback');

        $this->assertSame('fallback', $result);
        Log::shouldHaveReceived('error')->once();
    }

    public function test_swallow_returns_the_result_on_success(): void
    {
        $this->assertSame(7, FailurePolicy::swallow(static fn (): int => 7, 'Config'));
    }
}
