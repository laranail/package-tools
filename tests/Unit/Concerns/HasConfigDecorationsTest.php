<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Concerns;

use Orchestra\Testbench\TestCase;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Package;

/**
 * HasConfigDecorations: register-phase host-wins default merges and
 * boot-phase, failure-safe config decorators.
 */
final class HasConfigDecorationsTest extends TestCase
{
    private function package(): Package
    {
        return (new Package)
            ->name('acme/x')
            ->setPathFrom(__DIR__ . '/../../fixtures/config-decorations');
    }

    public function test_merges_config_defaults_lets_the_host_win(): void
    {
        config()->set('app.name', 'HostName');

        $package = $this->package();
        $package->mergesConfigDefaults('config/extra.php', 'app');
        $package->applyPackageConfigDefaults();

        // Host value survives; package-only default is added.
        $this->assertSame('HostName', config('app.name'));
        $this->assertSame('yes', config('app.pkg_only'));
    }

    public function test_merges_config_defaults_without_a_key_uses_the_file_map(): void
    {
        $package = $this->package();
        $package->mergesConfigDefaults('config/laravel.php');
        $package->applyPackageConfigDefaults();

        $this->assertTrue(config('app.pkg_flag'));
        $this->assertSame(1, config('custom.x'));
    }

    public function test_a_config_decorator_runs_at_boot(): void
    {
        $package = $this->package();
        $package->configDecorator(static fn ($c) => $c->set('acme.decorated', 'yes'));

        $package->bootPackageConfigDecorators();

        $this->assertSame('yes', config('acme.decorated'));
    }

    public function test_a_throwing_decorator_is_logged_and_skipped_not_fatal(): void
    {
        $package = $this->package();
        $package->configDecorator(static function (): never {
            throw new RuntimeException('boom');
        });
        $package->configDecorator(static fn ($c) => $c->set('acme.after', 'still-ran'));

        // Must not throw; the second decorator still runs.
        $package->bootPackageConfigDecorators();

        $this->assertSame('still-ran', config('acme.after'));
    }

    public function test_the_decorator_when_helper_is_conditional(): void
    {
        $package = $this->package();
        $package->configDecorator(static fn ($c) => $c
            ->when(true, static fn ($cc) => $cc->set('acme.yes', 1))
            ->when(false, static fn ($cc) => $cc->set('acme.no', 1)));

        $package->bootPackageConfigDecorators();

        $this->assertSame(1, config('acme.yes'));
        $this->assertNull(config('acme.no'));
    }
}
