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

    'logging' => [

        /*
        | Global defaults for every $package->log() logger. Per-package
        | overrides live at {vendor}.{package}.logging.* and win; a
        | host-defined logging.channels.{vendor}-{package} entry wins
        | wholesale. Keys left null defer to the package's LogDefinition
        | (or the built-in defaults: daily, 14 days, debug, line format,
        | storage/logs/{vendor}-{package}.log).
        */
        'enabled' => null,
        'channel' => null,     // delegate every package logger to one host channel
        'path' => null,
        'directory' => null,
        'driver' => null,      // 'daily' | 'single'
        'days' => null,
        'level' => null,
        'format' => null,      // 'line' | 'json'
        'permission' => null,

    ],

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

        /*
        | Defaults for background (queued) seeder bundles. null queue name
        | means the connection's default queue — nothing hardcoded.
        */
        'queue' => [
            'name' => null,
            'connection' => null,
            'tries' => 1,
            'timeout' => 300,
        ],

        /*
        | Kill-switch for the PackageSeedingStarted/Completed/Failed
        | events (per-bundle opt-out: notifiesOnCompletion(false)).
        */
        'events' => [
            'enabled' => true,
        ],

    ],

    'scheduling' => [

        /*
        | How a package's schedule-configuration failure (a bad cadence /
        | unknown scheduler method / throwing schedulesUsing() callback) is
        | handled when the Schedule resolves. Every failure is logged with
        | context regardless; this only controls whether it also throws.
        |
        |   true  → strict: rethrow so the author sees the typo immediately.
        |   false → lenient: skip the entry (one package's typo can't break
        |           the whole scheduler); other tasks still register.
        |   null  → auto: strict everywhere except production.
        */
        'strict' => env('PACKAGE_TOOLS_SCHEDULING_STRICT'),

    ],

];
