<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| laranail/package-tools
|--------------------------------------------------------------------------
|
| Host-app defaults for the package-tools runtime. Publish with:
|
|   php artisan vendor:publish --tag=package-tools-config
|
| Per-package overrides live under each consumer package's own config
| namespace ({vendor}.{package}.seeders.*, {vendor}.{package}.logging.*)
| and take precedence over the global defaults below.
|
*/

return [

    'seeders' => [

        /*
        | Extra root-seeder FQCNs whose resolution triggers package seeding
        | (in addition to Database\Seeders\DatabaseSeeder). db:seed with a
        | custom --class never triggers package bundles unless listed here.
        */
        'root_seeders' => [],

        'autorun' => [
            // Global kill-switch for autorun-after-migrations bundles.
            'enabled' => env('PACKAGE_TOOLS_SEEDERS_AUTORUN', true),

            // Autorun is skipped in production unless explicitly enabled
            // (a bundle's autorunInEnvironments() list overrides this).
            'in_production' => env('PACKAGE_TOOLS_SEEDERS_AUTORUN_PRODUCTION', false),

            // Autorun is skipped while running unit tests unless enabled —
            // RefreshDatabase migrations must not seed by surprise.
            'in_tests' => false,
        ],

    ],

];
