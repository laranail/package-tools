<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider;

use Illuminate\Foundation\Console\AboutCommand;

trait ProcessAboutSections
{
    /**
     * Register the package's declared `php artisan about` sections (guarded so
     * it is a no-op when the About command is unavailable).
     */
    protected function bootPackageAboutSections(): self
    {
        if (! class_exists(AboutCommand::class)) {
            return $this;
        }

        foreach ($this->package->aboutSections as $section) {
            AboutCommand::add($section['label'], $section['data']);
        }

        return $this;
    }
}
