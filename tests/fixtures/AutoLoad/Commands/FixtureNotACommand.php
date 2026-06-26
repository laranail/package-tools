<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Fixtures\AutoLoad\Commands;

/**
 * Plain class in the same directory as the fixture command. Must NOT be
 * picked up by autoLoadCommands() / discoverSubclasses(): it is not a
 * Console\Command subclass.
 */
class FixtureNotACommand
{
    public function noop(): void {}
}
