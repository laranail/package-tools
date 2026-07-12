<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Services\Boot;

use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\Package\Tools\Services\Boot\BootReport;

/**
 * BootReport: the observable, redacted degraded-boot surface (rule 7).
 */
final class BootReportTest extends TestCase
{
    public function test_a_fresh_report_is_healthy(): void
    {
        $this->assertTrue((new BootReport)->isHealthy());
    }

    public function test_recording_a_degraded_builder_makes_it_unhealthy_and_queryable(): void
    {
        $report = new BootReport;
        $report->recordDegraded('setLocale', 'Degradable', 'RuntimeException');

        $this->assertFalse($report->isHealthy());
        $this->assertArrayHasKey('setLocale', $report->degraded());
        $this->assertSame(
            ['criticality' => 'Degradable', 'cause_type' => 'RuntimeException'],
            $report->degraded()['setLocale'],
        );
    }
}
