<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

/**
 * ConfiguresDatabase — domain aggregator (ADR-004).
 *
 * Composer-related traits live in the dedicated `ConfiguresComposer`
 * aggregator and are not part of this database surface.
 */
trait ConfiguresDatabase
{
    use HasFactoriesAndSeeders;
    use HasMigrations;
}
