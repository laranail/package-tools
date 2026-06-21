<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Tests\Integration;

use Closure;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\PackageTools\Package;
use Simtabi\Laranail\PackageTools\Providers\PackageServiceProvider;
use Simtabi\Laranail\PackageTools\Tests\TestCase;

/**
 * End-to-end Testbench coverage of folder-tree / namespaced config
 * resolution: nested config files are merged into Laravel's config repo at
 * boot under a dotted key derived from their folder path, resolvable with a
 * native config() call.
 */
class NestedConfigResolutionTest extends TestCase
{
    private string $fixtureRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureRoot = dirname(__DIR__) . '/fixtures/nested-config-package';
    }

    #[Test]
    public function it_resolves_nested_configs_at_folder_derived_keys(): void
    {
        $this->makeProvider(function (Package $package): void {
            $package->discoversConfig();
        })->register();

        // Folder resolution (no __namespace in these files).
        $this->assertSame('Admin', config('admin.panel.title'));
        $this->assertSame(25, config('admin.panel.items_per_page'));
        $this->assertSame(60, config('api.v1.limits.rate'));
        $this->assertSame(100, config('api.v1.limits.burst'));
    }

    #[Test]
    public function it_honours_an_in_file_namespace_override_and_strips_the_key(): void
    {
        $this->makeProvider(function (Package $package): void {
            $package->discoversConfig();
        })->register();

        // config/custom/thing.php declares '__namespace' => 'acme.custom', so
        // it mounts at acme.custom.* rather than its folder key custom.thing.
        $this->assertTrue(config('acme.custom.enabled'));
        $this->assertSame('redis', config('acme.custom.driver'));

        // The folder-derived key is NOT used when an override is declared.
        $this->assertNull(config('custom.thing.enabled'));

        // The reserved key is stripped before merge: it must not leak.
        $this->assertNull(config('acme.custom.__namespace'));
    }

    #[Test]
    public function explicit_namespace_argument_prefixes_folder_keys(): void
    {
        $this->makeProvider(function (Package $package): void {
            $package->hasNestedConfig('panel', 'admin');
        })->register();

        // The builder-level $key override pins the mount point.
        $this->makeProvider(function (Package $package): void {
            $package->hasNestedConfig('panel', 'admin', 'dashboard.settings');
        })->register();

        $this->assertSame('Admin', config('admin.panel.title'));
        $this->assertSame('Admin', config('dashboard.settings.title'));
    }

    #[Test]
    public function app_config_wins_over_package_defaults(): void
    {
        // Pre-set the key before the provider registers: package values are
        // merged as defaults, so the app's value must survive.
        config(['admin.panel.title' => 'Overridden']);

        $this->makeProvider(function (Package $package): void {
            $package->discoversConfig();
        })->register();

        $this->assertSame('Overridden', config('admin.panel.title'));
        // Keys the app didn't set still fall through to the package default.
        $this->assertSame(25, config('admin.panel.items_per_page'));
    }

    #[Test]
    public function nested_keys_are_not_prefixed_with_the_vendor_package_namespace(): void
    {
        $this->makeProvider(function (Package $package): void {
            $package->discoversConfig();
        })->register();

        // Nested configs resolve at their bare folder key, never under the
        // acme.widget vendor/package prefix used for flat namespaced configs.
        $this->assertSame('Admin', config('admin.panel.title'));
        $this->assertNull(config('acme.widget.admin.panel.title'));
        $this->assertNull(config('acme.widget.admin.panel'));
    }

    #[Test]
    public function flat_config_files_still_mount_normally(): void
    {
        $this->makeProvider(function (Package $package): void {
            $package->hasConfigFile('widget');
        })->register();

        // Backward compatibility: a flat hasConfigFile() still merges. With a
        // vendor present, flat configs mount under the vendor.package prefix
        // (config('acme.widget.*')) — the legacy behaviour, untouched.
        $this->assertTrue(config('acme.widget.enabled'));
    }

    #[Test]
    public function nested_entries_publish_preserving_their_folder_layout(): void
    {
        $provider = $this->makeProvider(function (Package $package): void {
            $package->discoversConfig();
        });

        $provider->register();
        $provider->boot();

        // The provider runs in console under Testbench, so nested config
        // files register as publishables keyed by config_path(relative),
        // preserving the on-disk folder layout.
        $destinations = [];
        foreach (ServiceProvider::pathsToPublish($provider::class) as $destination) {
            $destinations[] = str_replace('\\', '/', $destination);
        }

        $matches = array_filter(
            $destinations,
            static fn (string $dest): bool => str_ends_with($dest, 'config/admin/panel.php')
                || str_ends_with($dest, 'config/api/v1/limits.php')
        );

        $this->assertCount(
            2,
            $matches,
            'Nested config files should publish to config_path() preserving folders.'
        );
    }

    #[Test]
    public function flat_namespaced_config_publishes_under_a_namespace_matching_path(): void
    {
        $provider = $this->makeProvider(function (Package $package): void {
            $package->hasConfigFile('widget');
        });

        $provider->register();
        $provider->boot();

        $destinations = [];
        foreach (ServiceProvider::pathsToPublish($provider::class) as $destination) {
            $destinations[] = str_replace('\\', '/', $destination);
        }

        // The flat config merges under the vendor.package key (acme.widget), so
        // its published override must land at config/acme/widget.php to load
        // back under config('acme.widget') — not at the flat config/widget.php,
        // which Laravel would load as config('widget') and never reach the
        // merged key.
        $namespaced = array_filter(
            $destinations,
            static fn (string $dest): bool => str_ends_with($dest, 'config/acme/widget.php')
        );

        $flat = array_filter(
            $destinations,
            static fn (string $dest): bool => str_ends_with($dest, 'config/widget.php')
        );

        $this->assertCount(1, $namespaced, 'Flat namespaced config should publish to config/<vendor>/<package>.php.');
        $this->assertCount(0, $flat, 'Flat namespaced config must not publish to the flat config/<package>.php.');
    }

    /**
     * Build an anonymous PackageServiceProvider whose package points at the
     * nested-config fixture, then runs the given configuration closure.
     */
    private function makeProvider(Closure $configure): PackageServiceProvider
    {
        $fixtureRoot = $this->fixtureRoot;

        return new class($this->app, $fixtureRoot, $configure) extends PackageServiceProvider
        {
            public function __construct(
                $app,
                private readonly string $fixtureRoot,
                private readonly Closure $configure,
            ) {
                parent::__construct($app);
            }

            public function configurePackage(Package $package): void
            {
                $package
                    ->setName('acme/widget')
                    ->setPublishTagId('acme')
                    // Re-point the base path at the fixture (the parent set it
                    // from the provider's own location first).
                    ->setPathFrom($this->fixtureRoot);

                ($this->configure)($package);
            }
        };
    }
}
