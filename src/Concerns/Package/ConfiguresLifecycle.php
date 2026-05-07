<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

/**
 * ConfiguresLifecycle — domain aggregator (ADR-004).
 *
 * Hosts the four `registeringPackage`, `packageRegistered`, `bootingPackage`,
 * `packageBooted` lifecycle methods consumers override, plus boot-time
 * discovery, batch loading, doctor checks, and validation.
 */
trait ConfiguresLifecycle
{
    use DiscoversWithAttributes;
    use HasBatchResourceLoading;
    use HasDoctorChecks;
    use HasEnhancedValidation;
    use HasLifecycleHooks;
}
