<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

/**
 * ConfiguresCommands — domain aggregator (ADR-004).
 */
trait ConfiguresCommands
{
    use HasCommands;
    use HasConsoleWrapper;
    use HasInstallCommand;
    use HasProgressIndicators;
}
