<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Concerns;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\App;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\Package\Tools\Package;

/**
 * HasRuntimeTweaks: useHttps / setLocale / paginator, all resolved and
 * applied at boot (never at configure time, so config-sourced values see the
 * merged package config).
 */
final class HasRuntimeTweaksTest extends TestCase
{
    public function test_use_https_forces_the_scheme_at_boot(): void
    {
        $package = (new Package)->name('acme/x');
        $package->useHttps();
        $package->bootPackageRuntimeTweaks();

        $this->assertStringStartsWith('https://', url('/foo'));
    }

    public function test_use_https_from_config_is_resolved_at_boot(): void
    {
        config()->set('acme.force_ssl', true);

        $package = (new Package)->name('acme/x');
        $package->useHttpsFromConfig('acme.force_ssl');
        $package->bootPackageRuntimeTweaks();

        $this->assertStringStartsWith('https://', url('/foo'));
    }

    public function test_set_locale_updates_the_application_locale(): void
    {
        $package = (new Package)->name('acme/x');
        $package->setLocale('fr');
        $package->bootPackageRuntimeTweaks();

        $this->assertSame('fr', App::getLocale());
    }

    public function test_set_locale_from_config_is_resolved_at_boot(): void
    {
        config()->set('app.locale', 'de');

        $package = (new Package)->name('acme/x');
        $package->setLocaleFromConfig('app.locale');
        $package->bootPackageRuntimeTweaks();

        $this->assertSame('de', App::getLocale());
    }

    public function test_the_paginator_sub_builder_sets_the_views(): void
    {
        $package = (new Package)->name('acme/x');
        $package->paginator()->setViews('acme::pagination.default', 'acme::pagination.simple');
        $package->bootPackageRuntimeTweaks();

        $this->assertSame('acme::pagination.default', Paginator::$defaultView);
        $this->assertSame('acme::pagination.simple', Paginator::$defaultSimpleView);

        Paginator::useTailwind();
    }

    public function test_the_paginator_sub_builder_chains_back_to_the_package(): void
    {
        $package = (new Package)->name('acme/x');

        // useHttps() lives on Package, not the sub-builder; __call delegates
        // it and keeps the chain on the sub-builder.
        $package->paginator()->setViews('acme::p.default', 'acme::p.simple')->useHttps();

        $package->bootPackageRuntimeTweaks();

        $this->assertSame('acme::p.default', Paginator::$defaultView);
        $this->assertStringStartsWith('https://', url('/foo'));

        Paginator::useTailwind();
    }
}
