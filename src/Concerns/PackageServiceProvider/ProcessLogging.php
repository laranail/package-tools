<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider;

use Simtabi\Laranail\Package\Tools\Services\Log\PackageLogger;
use Throwable;

trait ProcessLogging
{
    /**
     * Register-phase half: bind the container alias
     * (`app("laranail.logger.{vendor}-{package}")`) so app code can
     * resolve the logger without holding the Package.
     */
    protected function registerPackageLogging(): static
    {
        try {
            $alias = 'laranail.logger.' . $this->package->log()->channelName();
            $this->app->singleton($alias, fn (): PackageLogger => $this->package->log());
        } catch (Throwable) {
            // A name-less/malformed package fails validation later in
            // registerPackage(); logging setup must not preempt that error.
        }

        return $this;
    }

    /**
     * Boot-phase half, first in the boot chain: every provider has
     * registered and all config is final — flush the lines buffered since
     * configurePackage() against the now-authoritative settings.
     */
    protected function bootPackageLogging(): static
    {
        $this->package->markLoggerReady();

        return $this;
    }
}
