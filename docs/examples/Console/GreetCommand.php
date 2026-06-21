<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Example: a command registered by attribute discovery.
|------------------------------------------------------------------------------
| With ->discoversWithAttributes() set on the package, the builder scans src/
| for #[AsArtisanCommand] and registers each match via hasCommand(). No manual
| ->hasCommand(GreetCommand::class) call is needed.
|
| The attribute's `signature` is metadata for discovery; the command still
| needs its own $signature so Laravel can dispatch it.
*/

namespace Acme\Hello\Console;

use Illuminate\Console\Command;
use Simtabi\Laranail\Package\Tools\Attributes\AsArtisanCommand;

#[AsArtisanCommand(signature: 'hello:greet', description: 'Greet someone by name')]
final class GreetCommand extends Command
{
    protected $signature = 'hello:greet {name=friend : Who to greet}';

    protected $description = 'Greet someone by name';

    public function handle(): int
    {
        $this->info(sprintf('Hello, %s!', $this->argument('name')));

        return self::SUCCESS;
    }
}
