<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

/**
 * ConfiguresTranslations — domain aggregator (ADR-004).
 *
 * Wraps the single translations leaf trait. Aggregator kept for symmetry
 * across domains so Package only `use`s Configures* traits.
 */
trait ConfiguresTranslations
{
    use HasTranslations;
}
