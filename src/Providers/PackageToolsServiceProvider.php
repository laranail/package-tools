<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Providers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Override;
use Simtabi\Laranail\Package\Tools\Commands\PackageAuditCommand;
use Simtabi\Laranail\Package\Tools\Commands\PackageDoctorCommand;
use Simtabi\Laranail\Package\Tools\Commands\PackageIdeHelperCommand;
use Simtabi\Laranail\Package\Tools\Commands\PackageSbomCommand;
use Simtabi\Laranail\Package\Tools\Commands\PackageSeedCommand;
use Simtabi\Laranail\Package\Tools\Services\Database\Contracts\SeederConsoleFormatterInterface;
use Simtabi\Laranail\Package\Tools\Services\Database\FailureAwareMigrator;
use Simtabi\Laranail\Package\Tools\Services\Database\MigrationFailureDetector;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederAutorun;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederBuilder;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederConsoleFormatter;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederExecutor;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederManager;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederPathDiscoverer;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederRegistry;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederResolverHook;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorService;
use Simtabi\Laranail\Package\Tools\Services\Event\PackageActionReporter;
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

        // Central reporter behind the PackageActions facade — the single
        // choke point for the package-action lifecycle (start/success/fail),
        // reachable anywhere without a provider.
        $this->app->singleton(PackageActionReporter::class);

        // Conflict-free migration-lifecycle fallback (singleton so its
        // terminating-flush registers once); used only when another package
        // has already decorated the migrator.
        $this->app->singleton(MigrationFailureDetector::class);

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
                PackageSeedCommand::class,
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

            // Full-fidelity migration lifecycle (Laravel emits no
            // migration-failure event). Composition-safe — never clobbers
            // another package's migrator decoration.
            $this->wireMigrationFailureReporting();
        }

        // Root-seeder db:seed trigger — attached once here (not lazily on
        // first autoSeed()) so bundles registered at ANY point are seen.
        $this->app->make(SeederResolverHook::class)->attach();
    }

    /**
     * Decorate the `migrator` so every migration reports its lifecycle. When
     * we are the sole/first decorator, rebuild it as a
     * {@see FailureAwareMigrator} from the canonical container deps. When
     * another package already subclassed it, leave theirs alone and fall
     * back to the event-based {@see MigrationFailureDetector}. Idempotent.
     */
    private function wireMigrationFailureReporting(): void
    {
        if (! (bool) config('package-tools.migrations.failure_detection.enabled', true)) {
            return;
        }

        $this->app->extend('migrator', function (Migrator $migrator, Application $app): Migrator {
            // Already ours — nothing to do.
            if ($migrator instanceof FailureAwareMigrator) {
                return $migrator;
            }

            // A foreign subclass means another package decorated the migrator
            // first; rebuilding from the container would clobber it, so we
            // attach the conflict-free detector and hand theirs back intact.
            if ($migrator::class !== Migrator::class) {
                $app->make(MigrationFailureDetector::class)->register($app->make(Dispatcher::class), $app);

                return $migrator;
            }

            // Rebuild as our subclass from the same canonical dependencies the
            // framework uses (the repository is lifted off the existing
            // migrator; the rest resolve through their core aliases).
            return new FailureAwareMigrator(
                $app->make(PackageActionReporter::class),
                $migrator->getRepository(),
                $app->make(ConnectionResolverInterface::class),
                $app->make(Filesystem::class),
                $app->make(Dispatcher::class),
            );
        });
    }
}
