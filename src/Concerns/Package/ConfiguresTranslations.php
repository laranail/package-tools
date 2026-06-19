<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

/**
 * Translations domain aggregator. Wraps the single leaf trait; kept for
 * symmetry so Package only `use`s Configures* traits.
 */
trait ConfiguresTranslations
{
    use HasTranslations;
}
