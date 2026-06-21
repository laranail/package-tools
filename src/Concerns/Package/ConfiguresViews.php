<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

/**
 * Views domain aggregator.
 *
 * HasViewComposerRegistry stays unwired: its `registerViewComposer` collides
 * with HasEnhancedViewComposers.
 */
trait ConfiguresViews
{
    use HasEnhancedViewComposers;
    use HasViewComposers;
    use HasViews;
    use HasViewSharedData;
}
