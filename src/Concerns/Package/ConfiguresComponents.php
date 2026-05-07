<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

/**
 * ConfiguresComponents — domain aggregator (ADR-004).
 */
trait ConfiguresComponents
{
    use HasBladeComponents;
    use HasBladeDirectives;
    use HasComponentNamespaces;
    use HasEnhancedAnonymousComponents;
    use HasInertia;
    use HasLivewireComponents;
    use HasSafeComponentRegistration;
    use HasViewComponentLoader;
    use HasVueComponents;
}
