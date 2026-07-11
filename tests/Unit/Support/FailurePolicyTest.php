<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Support;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Orchestra\Testbench\TestCase;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Enums\BootCriticality;
use Simtabi\Laranail\Package\Tools\Exceptions\PackageBootException;
use Simtabi\Laranail\Package\Tools\Providers\PackageToolsServiceProvider;
use Simtabi\Laranail\Package\Tools\Services\Boot\BootReport;
use Simtabi\Laranail\Package\Tools\Support\Resilience\FailurePolicy;
use Throwable;

/**
 * The failure-handling runner: classify → report (guarded) → crash-on-critical
 * → record-and-continue-on-degradable, with no environment branch. Uses a spy
 * ExceptionHandler to assert what was reported (rule 12, no live monitoring).
 */
final class FailurePolicyTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [PackageToolsServiceProvider::class];
    }

    private function spyHandler(): object
    {
        $spy = new class implements ExceptionHandler
        {
            /** @var list<Throwable> */
            public array $reported = [];

            public bool $throwOnReport = false;

            public function report(Throwable $e): void
            {
                if ($this->throwOnReport) {
                    throw new RuntimeException('monitoring is down');
                }
                $this->reported[] = $e;
            }

            public function shouldReport(Throwable $e): bool
            {
                return true;
            }

            public function render($request, Throwable $e)
            {
                return null;
            }

            public function renderForConsole($output, Throwable $e): void {}
        };

        $this->app->instance(ExceptionHandler::class, $spy);

        return $spy;
    }

    public function test_run_returns_the_result_on_success(): void
    {
        $this->assertSame(42, FailurePolicy::run(static fn (): int => 42, 'thing'));
    }

    public function test_critical_reports_and_propagates_wrapped(): void
    {
        $spy = $this->spyHandler();

        try {
            FailurePolicy::run(static function (): never {
                throw new RuntimeException('scheme is bad');
            }, 'useHttps', BootCriticality::Critical);
            $this->fail('critical must propagate');
        } catch (PackageBootException $e) {
            $this->assertSame('useHttps', $e->builder);
            $this->assertSame(BootCriticality::Critical, $e->criticality);
            $this->assertStringContainsString('scheme is bad', $e->getMessage());
            $this->assertSame('crashed', $e->context()['decision']);
        }

        $this->assertCount(1, $spy->reported);
    }

    public function test_degradable_reports_records_and_continues(): void
    {
        $spy = $this->spyHandler();
        $report = $this->app->make(BootReport::class);

        $result = FailurePolicy::run(static function (): never {
            throw new RuntimeException('bad locale');
        }, 'setLocale', BootCriticality::Degradable);

        $this->assertNull($result);
        $this->assertCount(1, $spy->reported);
        $this->assertFalse($report->isHealthy());
        $this->assertArrayHasKey('setLocale', $report->degraded());
        // redacted: class only, never the raw message
        $this->assertSame(RuntimeException::class, $report->degraded()['setLocale']['cause_type']);
    }

    public function test_unclassified_defaults_to_critical(): void
    {
        $this->spyHandler();
        $this->expectException(PackageBootException::class);
        FailurePolicy::run(static function (): never {
            throw new RuntimeException('x');
        }, 'unknown'); // no criticality → Critical
    }

    public function test_a_reporting_failure_does_not_escalate(): void
    {
        $spy = $this->spyHandler();
        $spy->throwOnReport = true;
        $report = $this->app->make(BootReport::class);

        // report() throws internally → falls back to error_log, decision
        // unchanged: degradable still continues, does not escalate to a crash.
        $result = FailurePolicy::run(static function (): never {
            throw new RuntimeException('boom');
        }, 'setLocale', BootCriticality::Degradable);

        $this->assertNull($result);
        $this->assertArrayHasKey('setLocale', $report->degraded());
    }

    public function test_downstream_ops_skip_after_critical_but_run_after_degradable(): void
    {
        $this->spyHandler();
        $ran = [];

        // degradable failure → the next op still runs
        FailurePolicy::run(static function (): never {
            throw new RuntimeException('d');
        }, 'degradable', BootCriticality::Degradable);
        FailurePolicy::run(static function () use (&$ran): void {
            $ran[] = 'after-degradable';
        }, 'next');
        $this->assertContains('after-degradable', $ran);

        // critical failure → the caller (a loop/runner) stops; simulate: the
        // throw prevents the next line in the same try scope from running.
        try {
            FailurePolicy::run(static function (): never {
                throw new RuntimeException('c');
            }, 'critical', BootCriticality::Critical);
            $ran[] = 'after-critical';
        } catch (PackageBootException) {
            // expected — downstream in this scope did not run
        }
        $this->assertNotContains('after-critical', $ran);
    }

    public function test_warn_logs_and_never_throws(): void
    {
        // warn() is log-only and must not throw or touch the boot report.
        FailurePolicy::warn('a near-miss', ['expected' => 'x', 'actual' => 'y']);

        $this->assertTrue($this->app->make(BootReport::class)->isHealthy());
    }
}
