<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Example: an Artisan command shipped by a package built on package-tools.
|------------------------------------------------------------------------------
| Registered in HelloPackageServiceProvider via ->hasCommand(HelloCommand::class).
| Alternatively, drop a #[AsArtisanCommand] attribute on the class and let
| ->discoversWithAttributes() register it automatically (see
| docs/tools/attribute-discovery.md).
*/

namespace Acme\Hello\Console;

use Illuminate\Console\Command;

final class HelloCommand extends Command
{
    protected $signature = 'hello {name=world : Who to greet}';

    protected $description = 'Print a friendly greeting from the Hello package';

    public function handle(): int
    {
        $this->info(sprintf('Hello, %s!', $this->argument('name')));

        return self::SUCCESS;
    }
}
