<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Providers;

use Illuminate\Support\ServiceProvider;
use Override;
use Simtabi\Laranail\PackageTools\Commands\PackageAuditCommand;
use Simtabi\Laranail\PackageTools\Commands\PackageDoctorCommand;
use Simtabi\Laranail\PackageTools\Commands\PackageIdeHelperCommand;
use Simtabi\Laranail\PackageTools\Commands\PackageSbomCommand;
use Simtabi\Laranail\PackageTools\Services\Doctor\DoctorService;

/**
 * Auto-registers the four library-level Artisan commands shipped with
 * `laranail/package-tools`. Discovered via `extra.laravel.providers` in
 * composer.json so consumers get them for free.
 *
 * `package:doctor` requires per-Package wiring of `DoctorService` (consumers
 * register checks via `$package->hasDoctorCheck(...)`). The other three
 * commands are self-contained and act on the host project.
 */
final class LaranailToolsServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->singleton(DoctorService::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                PackageDoctorCommand::class,
                PackageSbomCommand::class,
                PackageAuditCommand::class,
                PackageIdeHelperCommand::class,
            ]);
        }
    }
}
