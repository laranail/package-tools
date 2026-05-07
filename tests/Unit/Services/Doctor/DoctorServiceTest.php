<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Tests\Unit\Services\Doctor;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Simtabi\Laranail\PackageTools\Services\Doctor\DoctorCheck;
use Simtabi\Laranail\PackageTools\Services\Doctor\DoctorResult;
use Simtabi\Laranail\PackageTools\Services\Doctor\DoctorService;
use Simtabi\Laranail\PackageTools\Services\Doctor\DoctorStatus;

final class PassingCheck implements DoctorCheck
{
    public function name(): string
    {
        return 'passing';
    }

    public function description(): string
    {
        return 'always passes';
    }

    public function run(): DoctorResult
    {
        return DoctorResult::pass('all good');
    }
}

final class FailingCheck implements DoctorCheck
{
    public function name(): string
    {
        return 'failing';
    }

    public function description(): string
    {
        return 'always fails';
    }

    public function run(): DoctorResult
    {
        return DoctorResult::fail('something broke');
    }
}

final class ThrowingCheck implements DoctorCheck
{
    public function name(): string
    {
        return 'throwing';
    }

    public function description(): string
    {
        return 'always throws';
    }

    public function run(): DoctorResult
    {
        throw new RuntimeException('boom');
    }
}

final class DoctorServiceTest extends TestCase
{
    public function test_register_accepts_instance(): void
    {
        $service = new DoctorService;
        $service->register(new PassingCheck);

        $this->assertCount(1, $service->getChecks());
    }

    public function test_register_accepts_class_string(): void
    {
        $service = new DoctorService;
        $service->register(PassingCheck::class);

        $this->assertCount(1, $service->getChecks());
        $this->assertInstanceOf(PassingCheck::class, $service->getChecks()[0]);
    }

    public function test_register_rejects_nonexistent_class(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new DoctorService)->register('Nope\\NotAClass');
    }

    public function test_run_returns_result_per_check(): void
    {
        $report = (new DoctorService)
            ->register(new PassingCheck)
            ->register(new FailingCheck)
            ->run();

        $this->assertCount(2, $report);
        $this->assertSame(DoctorStatus::Pass, $report[0]['result']->status);
        $this->assertSame(DoctorStatus::Fail, $report[1]['result']->status);
    }

    public function test_throwing_check_becomes_fai_l_with_exception_detail(): void
    {
        $report = (new DoctorService)
            ->register(new ThrowingCheck)
            ->run();

        $this->assertSame(DoctorStatus::Fail, $report[0]['result']->status);
        $this->assertStringContainsString('boom', $report[0]['result']->message);
        $this->assertSame(RuntimeException::class, $report[0]['result']->detail['exception']);
    }

    public function test_summarise_counts_each_status(): void
    {
        $service = (new DoctorService)
            ->register(new PassingCheck)
            ->register(new PassingCheck)
            ->register(new FailingCheck);

        $report = $service->run();
        $counts = $service->summarise($report);

        $this->assertSame(['pass' => 2, 'warn' => 0, 'fail' => 1, 'skip' => 0], $counts);
    }

    public function test_doctor_status_has_symbol_and_color(): void
    {
        $this->assertSame('✓', DoctorStatus::Pass->symbol());
        $this->assertSame('✗', DoctorStatus::Fail->symbol());
        $this->assertSame('!', DoctorStatus::Warn->symbol());
        $this->assertSame('·', DoctorStatus::Skip->symbol());

        $this->assertStringContainsString('32', DoctorStatus::Pass->ansiColor());
        $this->assertStringContainsString('31', DoctorStatus::Fail->ansiColor());
    }

    public function test_doctor_result_factory_methods(): void
    {
        $this->assertSame(DoctorStatus::Pass, DoctorResult::pass('ok')->status);
        $this->assertSame(DoctorStatus::Warn, DoctorResult::warn('eh')->status);
        $this->assertSame(DoctorStatus::Fail, DoctorResult::fail('no')->status);
        $this->assertSame(DoctorStatus::Skip, DoctorResult::skip('na')->status);
    }
}
