<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Commands;

use Illuminate\Console\Command as BaseCommand;
use Simtabi\Laranail\Package\Tools\Commands\Concerns\ReadsOptions;
use Simtabi\Laranail\Package\Tools\Commands\Concerns\SupportsNamespacedNames;

/**
 * Base Artisan command for laranail packages.
 *
 * Extends Laravel's command and mixes in two concerns:
 *
 * - {@see SupportsNamespacedNames} accepts the `laranail::package-tools.<command>`
 *   namespace separator (and plain `:`).
 * - {@see ReadsOptions} normalises console input -- `stringOption()` lived on
 *   this class directly until it gained siblings that answer the same question
 *   differently; it is unchanged, and a subclass sees the same method it always
 *   did.
 *
 * Extend this, or `use` either concern on a command that already extends
 * something else.
 */
abstract class Command extends BaseCommand
{
    use ReadsOptions;
    use SupportsNamespacedNames;
}
