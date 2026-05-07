<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

/**
 * ConfiguresAssets — domain aggregator (ADR-004).
 *
 * HasAssetGroups + HasModuleAssets remain deferred (ADR-0011): they collide
 * with HasAssetPublisher (`$assetGroups`, `getAssetGroups()`,
 * `publishModuleAssets()`).
 */
trait ConfiguresAssets
{
    use HasAssetCleanup;
    use HasAssetPublisher;
    use HasAssets;
    use HasVueAssets;
}
