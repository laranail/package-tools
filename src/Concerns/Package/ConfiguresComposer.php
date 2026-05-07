<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

/**
 * ConfiguresComposer — domain aggregator (ADR-004).
 *
 * Runtime-side Composer interactions: install/update/require/remove +
 * composer.json validation. Scaffolder-time `ManagesComposer` lives in
 * package-scaffolder and is intentionally not aggregated here (ADR-0011).
 */
trait ConfiguresComposer
{
    use HasComposerOperations;
}
