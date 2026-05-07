<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\PackageServiceProvider;

use Illuminate\Support\Facades\Blade;

trait ProcessBladeDirectives
{
    /**
     * Boot Blade directives
     *
     * Registers all Blade directives defined in the package configuration.
     */
    protected function bootPackageBladeDirectives(): self
    {
        if (empty($this->package->bladeDirectives)) {
            return $this;
        }

        foreach ($this->package->bladeDirectives as $name => $handler) {
            Blade::directive($name, $handler);
        }

        return $this;
    }
}
