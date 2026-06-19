<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

/**
 * Asset domain aggregator.
 *
 * HasAssetGroups and HasModuleAssets stay out: they collide with
 * HasAssetPublisher (`$assetGroups`, `getAssetGroups()`,
 * `publishModuleAssets()`).
 */
trait ConfiguresAssets
{
    use HasAssetCleanup;
    use HasAssetPublisher;
    use HasAssets;
    use HasVueAssets;
}
