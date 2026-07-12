<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature;

use Override;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\Package\Tools\Providers\PackageToolsServiceProvider;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorResult;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorService;
use Simtabi\Laranail\Package\Tools\Support\Definitions\DoctorCheckDefinition;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

final class DoctorDefinitionsTestPackageProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('acme/doctor-definitions')
            ->hasDoctorChecks([
                DoctorCheckDefinition::callback('always:on', fn (): DoctorResult => DoctorResult::pass('ok')),
                DoctorCheckDefinition::callback('gated:off', fn (): DoctorResult => DoctorResult::pass('hidden'))
                    ->whenConfig('doctor_defs.gated', false),
            ]);
    }
}

final class BootPackageDoctorDefinitionsTest extends TestCase
{
    #[Override]
    protected function getPackageProviders($app): array
    {
        return [PackageToolsServiceProvider::class, DoctorDefinitionsTestPackageProvider::class];
    }

    public function test_definitions_register_with_package_attribution(): void
    {
        $report = $this->app->make(DoctorService::class)->run();

        $names = array_map(static fn (array $row): string => $row['check']->name(), $report);

        $this->assertContains('always:on', $names);

        $row = $report[array_search('always:on', $names, true)];
        $this->assertSame('acme/doctor-definitions', $row['group']);
    }

    public function test_gated_off_definitions_are_never_registered(): void
    {
        $report = $this->app->make(DoctorService::class)->run();

        $names = array_map(static fn (array $row): string => $row['check']->name(), $report);

        $this->assertNotContains('gated:off', $names);
    }
}
