<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Concerns;

use Orchestra\Testbench\TestCase;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Enums\BootCriticality;
use Simtabi\Laranail\Package\Tools\Exceptions\PackageBootException;
use Simtabi\Laranail\Package\Tools\Package;

/**
 * HasConfigDecorations: register-phase host-wins default merges and
 * boot-phase config decorators (Critical by default, Degradable opt-in).
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

    public function test_a_throwing_decorator_fails_closed_by_default(): void
    {
        // A config decoration is a general escape hatch → Critical by default:
        // a throw crashes boot (fail fast) rather than silently degrading.
        $package = $this->package();
        $package->configDecorator(static function (): never {
            throw new RuntimeException('boom');
        });

        $this->expectException(PackageBootException::class);

        $package->bootPackageConfigDecorators();
    }

    public function test_a_decorator_marked_degradable_is_skipped_and_the_next_runs(): void
    {
        // An author who declares a decoration cosmetic (Degradable) gets
        // report-and-continue: the throw is skipped and the next runs.
        $package = $this->package();
        $package->configDecorator(static function (): never {
            throw new RuntimeException('boom');
        }, BootCriticality::Degradable);
        $package->configDecorator(static fn ($c) => $c->set('acme.after', 'still-ran'));

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
