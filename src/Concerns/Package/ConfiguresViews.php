<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

/**
 * ConfiguresViews — domain aggregator (ADR-004).
 *
 * HasViewComposerRegistry stays unwired (ADR-0011): its `registerViewComposer`
 * collides with HasEnhancedViewComposers.
 */
trait ConfiguresViews
{
    use HasEnhancedViewComposers;
    use HasViewComposers;
    use HasViews;
    use HasViewSharedData;
}
