<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

/**
 * ConfiguresConfig — domain aggregator (ADR-004).
 *
 * HasCachedNamespaces remains deferred (ADR-0011): four of its methods collide
 * with HasConfigNamespace.
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
