<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Example: a package seeder run through the seeder plumbing.
|------------------------------------------------------------------------------
| Registered in HelloPackageServiceProvider via:
|
|   ->hasPackageSeeders(
|       'acme/hello',
|       [GreetingSeeder::class],
|       namespace: 'Acme\\Hello\\Database\\Seeders',
|       options: ['fire_events' => true],
|   )
|
| The SeederExecutor runs every registered bundle when the host app seeds.
| With fire_events on, it dispatches:
|   Simtabi\Laranail\Package\Tools\Events\SeedingStarted  (once, before any run)
|   Simtabi\Laranail\Package\Tools\Events\SeedingFinished (once, with success/failure counts)
|
| To discover seeders by directory instead of listing them, use:
|   ->discoverPackageSeedersIn(__DIR__, namespace: 'Acme\\Hello\\Database\\Seeders')
*/

namespace Acme\Hello\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class GreetingSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('greetings')->insert([
            ['phrase' => 'Hello', 'locale' => 'en'],
            ['phrase' => 'Hola', 'locale' => 'es'],
            ['phrase' => 'Habari', 'locale' => 'sw'],
        ]);
    }
}
