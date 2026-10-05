<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Commands;

use Illuminate\Console\Command as BaseCommand;
use Simtabi\Laranail\Package\Tools\Commands\Concerns\ReadsOptions;
use Simtabi\Laranail\Package\Tools\Commands\Concerns\SupportsNamespacedNames;
use Simtabi\Laranail\Package\Tools\Commands\Concerns\WarnsOnDeprecatedAliases;

/**
 * Base Artisan command for laranail packages.
 *
 * Extends Laravel's command and mixes in three concerns:
 *
 * - {@see SupportsNamespacedNames} accepts the `laranail::package-tools.<command>`
 *   namespace separator (and plain `:`).
 * - {@see ReadsOptions} normalises console input -- `stringOption()` lived on
 *   this class directly until it gained siblings that answer the same question
 *   differently; it is unchanged, and a subclass sees the same method it always
 *   did.
 * - {@see WarnsOnDeprecatedAliases} prints a one-line deprecation when the
 *   command is invoked by an alias outside its vendor scope (or one listed in
 *   `deprecatedAliases()`), then runs it as before.
 *
 * Extend this, or `use` any of these concerns on a command that already extends
 * something else.
 */
abstract class Command extends BaseCommand
{
    use ReadsOptions;
    use SupportsNamespacedNames;
    use WarnsOnDeprecatedAliases;
}
