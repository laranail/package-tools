<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Providers;

use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Override;
use Simtabi\Laranail\Package\Tools\Commands\PackageAuditCommand;
use Simtabi\Laranail\Package\Tools\Commands\PackageDoctorCommand;
use Simtabi\Laranail\Package\Tools\Commands\PackageIdeHelperCommand;
use Simtabi\Laranail\Package\Tools\Commands\PackageSbomCommand;
use Simtabi\Laranail\Package\Tools\Services\Database\Contracts\SeederConsoleFormatterInterface;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederAutorun;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederBuilder;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederConsoleFormatter;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederExecutor;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederManager;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederPathDiscoverer;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederRegistry;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederResolverHook;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorService;
use Simtabi\Laranail\Package\Tools\Services\Http\Contracts\HttpConfigurationServiceInterface;
use Simtabi\Laranail\Package\Tools\Services\Http\HttpConfigurationService;
use Simtabi\Laranail\Package\Tools\Services\System\Contracts\SystemServiceInterface;
use Simtabi\Laranail\Package\Tools\Services\System\SystemService;
use Simtabi\Laranail\Package\Tools\Support\ErrorStorage\Contracts\ErrorStorageServiceInterface;
use Simtabi\Laranail\Package\Tools\Support\ErrorStorage\ErrorStorageService;

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
final class PackageToolsServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/package-tools.php', 'package-tools');

        $this->app->singleton(DoctorService::class);

        // Standalone seeding API (shared registry so autoSeed() and the
        // resolver hook see the same configurations).
        $this->app->singleton(SeederRegistry::class);
        // Formatter stays opt-in: pass a SeederConsoleFormatter explicitly
        // (with an OutputStyle) when you want tree-structured output.
        $this->app->singleton(SeederExecutor::class, static fn ($app): SeederExecutor => new SeederExecutor($app));
        $this->app->singleton(SeederAutorun::class);
        $this->app->singleton(SeederPathDiscoverer::class);
        $this->app->singleton(SeederManager::class);
        $this->app->bind(SeederBuilder::class, static fn ($app): SeederBuilder => $app->make(SeederManager::class)->seeders());
        $this->app->singleton(SeederConsoleFormatterInterface::class, static fn (): SeederConsoleFormatter => new SeederConsoleFormatter);
        $this->app->singleton(SeederResolverHook::class);

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

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/package-tools.php' => config_path('package-tools.php'),
            ], 'package-tools-config');

            // Opt-in post-migration autorun: fires once per Migrator batch,
            // including nested `$command->call('migrate')` (install commands).
            Event::listen(MigrationsEnded::class, [SeederAutorun::class, 'handleMigrationsEnded']);
        }

        // Root-seeder db:seed trigger — attached once here (not lazily on
        // first autoSeed()) so bundles registered at ANY point are seen.
        $this->app->make(SeederResolverHook::class)->attach();
    }
}
