<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\Package\Tools\Providers\PackageToolsServiceProvider;
use Simtabi\Laranail\Package\Tools\Services\Boot\BootReport;

final class DegradedBootTestProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        // A Degradable builder that fails at boot: it reports + records
        // degraded + continues (does not crash), so it is observable.
        $package
            ->name('acme/degraded')
            ->setLocale(static function (): string {
                throw new RuntimeException('locale resolver blew up');
            });
    }
}

/**
 * The CI health gate (rule 12): a degradable boot failure does not crash, but
 * it IS recorded on the observable BootReport and surfaces in the doctor
 * check — so a normal CI run over a degraded boot fails without relying on a
 * dev-only crash.
 */
final class BootHealthGateTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [PackageToolsServiceProvider::class, DegradedBootTestProvider::class];
    }

    public function test_a_degradable_boot_failure_is_recorded_not_crashed(): void
    {
        // Boot happened during setUp without throwing (degradable), but the
        // degraded state is queryable — this is the assertion a CI gate runs.
        $report = $this->app->make(BootReport::class);

        $this->assertFalse($report->isHealthy());
        $this->assertArrayHasKey('setLocale', $report->degraded());
    }

    public function test_the_doctor_boot_health_check_fails_on_degraded_boot(): void
    {
        $exit = Artisan::call('laranail::package-tools.doctor');
        $output = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('boot:health', $output);
        $this->assertStringContainsString('setLocale', $output);
    }
}
