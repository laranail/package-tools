<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

/**
 * Command domain aggregator.
 */
trait ConfiguresCommands
{
    use HasCommands;
    use HasConsoleWrapper;
    use HasInstallCommand;
    use HasProgressIndicators;
}
