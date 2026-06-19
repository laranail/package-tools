<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

/**
 * Config domain aggregator.
 *
 * HasCachedNamespaces stays out: four of its methods collide with
 * HasConfigNamespace.
 */
trait ConfiguresConfig
{
    use HasAdditionalNamespaceFormats;
    use HasAdvancedConfig;
    use HasConfigManipulation;
    use HasConfigNamespace;
    use HasConfigs;
    use HasGlobalConfigMerging;
    use HasNestedConfigFiles;
}
