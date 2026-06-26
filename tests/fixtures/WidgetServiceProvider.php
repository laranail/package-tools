<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Fixtures;

use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;

/**
 * A concrete (named) provider over the nested-config-package fixture, so tests
 * that need a `class-string` provider (e.g. AssertsPublishedConfigOverrides) can
 * reference one. Mirrors the anonymous provider used elsewhere.
 */
class WidgetServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->setName('acme/widget')
            ->setPublishTagId('acme')
            ->setPathFrom(__DIR__ . '/nested-config-package')
            ->hasConfigFile('widget');
    }
}
