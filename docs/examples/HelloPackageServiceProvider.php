<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Example: minimal package built on laranail/package-tools.
|------------------------------------------------------------------------------
| Drop this into any Laravel 13+ app's package directory (or a fresh
| package skeleton from laranail/package-scaffolder), adjust the namespace
| to match your composer.json autoload, and you have a working package
| with config publishing, view discovery, an Artisan command, and a
| `package:doctor` health check — all in <40 lines.
*/

namespace Acme\Hello;

use Acme\Hello\Console\HelloCommand;
use Acme\Hello\Doctor\HelloHealthCheck;
use Simtabi\Laranail\PackageTools\Package;
use Simtabi\Laranail\PackageTools\PackageServiceProvider;

final class HelloPackageServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('acme/hello')
            ->hasConfigFile()                                         // publishes config/hello.php
            ->hasViews()                                              // resources/views
            ->hasTranslations()                                       // resources/lang
            ->hasMigration('create_hellos_table')                     // database/migrations/...
            ->hasCommand(HelloCommand::class)                         // app php artisan hello
            ->hasInstallCommand(fn ($cmd) => $cmd
                ->publishConfigFile()
                ->publishMigrations()
                ->askToRunMigrations()
                ->askToStarRepoOnGitHub('acme/hello'))

            // v1.0 differentiators (ADR-009 + ADR-006):
            ->discoversWithAttributes()                               // scan src/ for #[AsArtisanCommand], etc.
            ->hasDoctorCheck(HelloHealthCheck::class);                // wires into php artisan package:doctor
    }
}
