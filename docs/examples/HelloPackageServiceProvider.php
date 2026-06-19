<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Example: a fuller package built on laranail/package-tools.
|------------------------------------------------------------------------------
| This walks a realistic slice of the fluent builder and both styles of
| lifecycle hook. Drop it into a Laravel 13+ app's package directory (or a
| fresh skeleton from laranail/package-scaffolder), point the namespace at
| your composer.json autoload, and you have a package with config, views,
| components, translations, assets, routes, migrations, commands, an install
| command, attribute discovery, seeders, and a doctor check.
|
| Companion example files (same Acme\Hello namespace):
|   Console/HelloCommand.php       a plain Artisan command (hasCommand)
|   Console/GreetCommand.php       #[AsArtisanCommand] discovered command
|   Console/SyncCommand.php        namespaced `acme::hello.sync` command
|   Http/WidgetController.php      WebController + #[AsRoute]
|   Http/WidgetApiController.php   ApiController JSON helpers
|   Http/GreetingComposer.php      #[AsViewComposer]
|   Contracts/GreeterContract.php  #[AsFacade] for the ide-helper command
|   Doctor/HelloHealthCheck.php    config-published check
|   Doctor/StorageWritableCheck.php  warn/fail/skip edge cases
|   Jobs/SyncGreetingsJob.php      HasGuzzleConfig + HasErrorStorage
|   Database/Seeders/GreetingSeeder.php  a package seeder
*/

namespace Acme\Hello;

use Acme\Hello\Console\HelloCommand;
use Acme\Hello\Contracts\GreeterContract;
use Acme\Hello\Database\Seeders\GreetingSeeder;
use Acme\Hello\Doctor\HelloHealthCheck;
use Acme\Hello\Doctor\StorageWritableCheck;
use Acme\Hello\Http\GreetingComposer;
use Acme\Hello\Support\Greeter;
use Illuminate\Support\Facades\Log;
use Simtabi\Laranail\PackageTools\Package;
use Simtabi\Laranail\PackageTools\PackageServiceProvider;

final class HelloPackageServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('acme/hello')                                      // vendor extracted, short name is "hello"
            ->hasConfigFile()                                         // publishes config/hello.php
            ->hasViews()                                              // resources/views, namespaced "hello"
            ->hasViewComponents('hello', GreetingComposer::class)     // <x-hello-...> Blade components by prefix
            ->hasTranslations()                                       // resources/lang
            ->hasAssets()                                             // resources/assets -> public/vendor/hello
            ->hasRoute('web')                                         // routes/web.php
            ->hasMigration('create_greetings_table')                  // database/migrations/...create_greetings_table.php
            ->runsMigrations()                                        // auto-run package migrations on boot
            ->discoversMigrations()                                   // also pick up any other files in database/migrations
            ->hasCommand(HelloCommand::class)                         // php artisan hello

            // Package seeders: run alongside the host app's DatabaseSeeder.
            // fire_events emits SeedingStarted / SeedingFinished.
            ->hasPackageSeeders(
                'acme/hello',
                [GreetingSeeder::class],
                namespace: 'Acme\\Hello\\Database\\Seeders',
                options: ['fire_events' => true],
            )

            // Install command: `php artisan hello:install`. Each step maps to
            // a concern under src/Commands/Concerns/.
            ->hasInstallCommand(fn ($command) => $command
                ->startWith(fn ($cmd) => $cmd->info('Installing the Hello package...'))
                ->publishConfigFile()                                 // vendor:publish --tag=hello-config
                ->publishMigrations()                                 // vendor:publish --tag=hello-migrations
                ->publishAssets()                                     // vendor:publish --tag=hello-assets
                ->askToRunMigrations()                                // prompts, then `php artisan migrate`
                ->askToStarRepoOnGitHub('acme/hello')                 // opens the repo in a browser if confirmed
                ->endWith(fn ($cmd) => $cmd->info('Read the docs at https://opensource.simtabi.com/documentation/laranail/package-tools')))

            // Optionally ship the provider itself so apps can publish & edit it.
            ->publishesServiceProvider('HelloServiceProvider')

            // Scan src/ for #[AsArtisanCommand], #[AsRoute], #[AsViewComposer].
            ->discoversWithAttributes()

            // Two doctor checks for `php artisan laranail::package-tools.doctor`.
            ->hasDoctorCheck(HelloHealthCheck::class)
            ->hasDoctorCheck(StorageWritableCheck::class);

        // Closure lifecycle hooks live on the Package object. They receive the
        // Package instance and fire at the matching point in the boot sequence.
        $package
            ->onBeforeBoot(fn (Package $p) => Log::debug('hello: booting', ['name' => $p->shortName()]))
            ->onAfterBoot(fn (Package $p) => Log::debug('hello: booted'));
    }

    /**
     * Override hook: runs after the package registers. Good for container
     * bindings that the rest of the package depends on.
     */
    public function packageRegistered(): void
    {
        $this->app->singleton(GreeterContract::class, Greeter::class);
    }

    /**
     * Override hook: runs after the package finishes booting. Good for work
     * that needs booted services (events, view shares, scheduled tasks).
     */
    public function packageBooted(): void
    {
        // e.g. share a default greeting with every view, register a listener…
    }
}
