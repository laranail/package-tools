<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature\Commands;

use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\Package\Tools\Providers\PackageToolsServiceProvider;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorResult;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorService;

final class PackageDoctorCommandTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [PackageToolsServiceProvider::class];
    }

    public function test_doctor_passes_when_no_checks_registered(): void
    {
        $exit = Artisan::call('laranail::package-tools.doctor');
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('No doctor checks registered', $output);
    }

    public function test_doctor_passes_when_all_checks_pass(): void
    {
        $service = $this->app->make(DoctorService::class);
        $service->register(new class implements DoctorCheck
        {
            public function name(): string
            {
                return 'sample.pass';
            }

            public function description(): string
            {
                return 'always passes';
            }

            public function run(): DoctorResult
            {
                return DoctorResult::pass('all good');
            }
        });

        $exit = Artisan::call('laranail::package-tools.doctor');

        $this->assertSame(0, $exit);
    }

    public function test_doctor_fails_when_any_check_fails(): void
    {
        $service = $this->app->make(DoctorService::class);
        $service->register(new class implements DoctorCheck
        {
            public function name(): string
            {
                return 'sample.fail';
            }

            public function description(): string
            {
                return 'always fails';
            }

            public function run(): DoctorResult
            {
                return DoctorResult::fail('something broke');
            }
        });

        $exit = Artisan::call('laranail::package-tools.doctor');
        $output = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('something broke', $output);
    }

    public function test_json_flag_emits_machine_readable_output(): void
    {
        $service = $this->app->make(DoctorService::class);
        $service->register(new class implements DoctorCheck
        {
            public function name(): string
            {
                return 'sample.pass';
            }

            public function description(): string
            {
                return 'always passes';
            }

            public function run(): DoctorResult
            {
                return DoctorResult::pass('ok');
            }
        });

        Artisan::call('laranail::package-tools.doctor', ['--json' => true]);
        $output = Artisan::output();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertSame(1, $decoded['summary']['pass']);
    }
}
