<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\Package\Tools\Providers\PackageToolsServiceProvider;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorResult;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorService;

final class WiringTestCheck implements DoctorCheck
{
    public function name(): string
    {
        return 'wiring.test';
    }

    public function description(): string
    {
        return 'verifies hasDoctorCheck() wiring';
    }

    public function run(): DoctorResult
    {
        return DoctorResult::pass('wired');
    }
}

final class WiringTestServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laranail/wiring-test')
            ->hasDoctorCheck(WiringTestCheck::class);
    }
}

final class BootPackageDoctorChecksTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [PackageToolsServiceProvider::class, WiringTestServiceProvider::class];
    }

    public function test_has_doctor_check_is_wired_into_the_doctor_service_after_boot(): void
    {
        $service = $this->app->make(DoctorService::class);

        $names = array_map(static fn (DoctorCheck $c): string => $c->name(), $service->getChecks());

        $this->assertContains('wiring.test', $names);
    }

    public function test_wired_check_appears_in_the_unified_doctor_command(): void
    {
        Artisan::call('laranail::package-tools.doctor');

        $this->assertStringContainsString('wiring.test', Artisan::output());
    }
}
