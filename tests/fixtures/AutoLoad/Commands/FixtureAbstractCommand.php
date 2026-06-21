<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Fixtures\AutoLoad\Commands;

use Illuminate\Console\Command;

/**
 * Abstract Console\Command subclass. Must NOT be picked up: it is not
 * instantiable.
 */
abstract class FixtureAbstractCommand extends Command
{
    abstract protected function doWork(): int;
}
