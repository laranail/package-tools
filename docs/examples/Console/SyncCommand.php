<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Example: a consumer command using the laranail `::` namespace separator.
|------------------------------------------------------------------------------
| Extending the package's base Command (or using SupportsNamespacedNames on a
| plain Illuminate command) lets a signature carry the `::` separator that
| Symfony would otherwise reject. The same `laranail::package-tools.<command>`
| convention the library uses for its own commands applies to consumers, so a
| package can group its commands under a stable, vendor-scoped prefix.
|
| Dispatch with either spelling:
|   php artisan acme::hello.sync
|   php artisan acme:hello.sync
*/

namespace Acme\Hello\Console;

use Simtabi\Laranail\PackageTools\Commands\Command;

final class SyncCommand extends Command
{
    protected $signature = 'acme::hello.sync {--since= : Only sync greetings created after this date}';

    protected $description = 'Sync greetings from the upstream service';

    public function handle(): int
    {
        $since = $this->option('since') ?: 'the beginning of time';

        $this->info("Syncing greetings created since {$since}...");

        return self::SUCCESS;
    }
}
