<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Fixtures\AutoLoad\Commands;

use Illuminate\Console\Command;

/**
 * Fixture command for autoLoadCommands() / discoverSubclasses() tests.
 */
class FixtureFooCommand extends Command
{
    protected $signature = 'fixture:foo';

    protected $description = 'Fixture command for auto-load tests.';

    public function handle(): int
    {
        return self::SUCCESS;
    }
}
