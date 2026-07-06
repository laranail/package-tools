<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider;

trait ProcessRoutes
{
    protected function bootPackageRoutes(): self
    {
        if (empty($this->package->routeFileNames) && empty($this->package->conditionalRouteFileNames)) {
            return $this;
        }

        foreach ($this->package->routeFileNames as $routeFileName) {
            $this->loadRoutesFrom("{$this->package->basePath('/routes/')}{$routeFileName}.php");
        }

        foreach ($this->package->conditionalRouteFileNames as $conditional) {
            if (! (bool) config($conditional['key'], $conditional['default'])) {
                continue;
            }

            foreach ($conditional['files'] as $routeFileName) {
                $this->loadRoutesFrom("{$this->package->basePath('/routes/')}{$routeFileName}.php");
            }
        }

        return $this;
    }
}
