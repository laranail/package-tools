<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Providers;

use Illuminate\Support\ServiceProvider;
use Override;
use Simtabi\Laranail\PackageTools\Commands\PackageAuditCommand;
use Simtabi\Laranail\PackageTools\Commands\PackageDoctorCommand;
use Simtabi\Laranail\PackageTools\Commands\PackageIdeHelperCommand;
use Simtabi\Laranail\PackageTools\Commands\PackageSbomCommand;
use Simtabi\Laranail\PackageTools\Services\Database\Contracts\SeederConsoleFormatterInterface;
use Simtabi\Laranail\PackageTools\Services\Database\SeederBuilder;
use Simtabi\Laranail\PackageTools\Services\Database\SeederConsoleFormatter;
use Simtabi\Laranail\PackageTools\Services\Database\SeederExecutor;
use Simtabi\Laranail\PackageTools\Services\Database\SeederManager;
use Simtabi\Laranail\PackageTools\Services\Database\SeederRegistry;
use Simtabi\Laranail\PackageTools\Services\Doctor\DoctorService;
use Simtabi\Laranail\PackageTools\Services\Http\Contracts\HttpConfigurationServiceInterface;
use Simtabi\Laranail\PackageTools\Services\Http\HttpConfigurationService;
use Simtabi\Laranail\PackageTools\Services\System\Contracts\SystemServiceInterface;
use Simtabi\Laranail\PackageTools\Services\System\SystemService;
use Simtabi\Laranail\PackageTools\Support\ErrorStorage\Contracts\ErrorStorageServiceInterface;
use Simtabi\Laranail\PackageTools\Support\ErrorStorage\ErrorStorageService;

/**
 * Auto-registers the four library-level Artisan commands plus the three
 * runtime services (system inspector, HTTP options builder, error bag)
 * shipped with `laranail/package-tools`. Discovered via
 * `extra.laravel.providers` in composer.json.
 *
 * `laranail::package-tools.doctor` needs per-Package wiring of
 * `DoctorService` (consumers register checks via
 * `$package->hasDoctorCheck(...)`). The other three commands are
 * self-contained and act on the host project.
 */
final class LaranailToolsServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->singleton(DoctorService::class);

        // Standalone seeding API (shared registry so autoSeed() and the
        // resolver hook see the same configurations).
        $this->app->singleton(SeederRegistry::class);
        // Formatter stays opt-in: pass a SeederConsoleFormatter explicitly
        // (with an OutputStyle) when you want tree-structured output.
        $this->app->singleton(SeederExecutor::class, static fn ($app): SeederExecutor => new SeederExecutor($app));
        $this->app->singleton(SeederManager::class);
        $this->app->bind(SeederBuilder::class, static fn ($app): SeederBuilder => $app->make(SeederManager::class)->seeders());
        $this->app->singleton(SeederConsoleFormatterInterface::class, static fn (): SeederConsoleFormatter => new SeederConsoleFormatter);

        // SystemService is request-scoped; its output depends on $_SERVER.
        $this->app->bind(
            SystemServiceInterface::class,
            fn ($app): SystemService => new SystemService($app),
        );

        // HTTP options builder is a singleton with env-driven defaults.
        $this->app->singleton(
            HttpConfigurationServiceInterface::class,
            HttpConfigurationService::class,
        );

        // Error bag is per-resolution (each install command gets a clean bag).
        $this->app->bind(
            ErrorStorageServiceInterface::class,
            ErrorStorageService::class,
        );

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
