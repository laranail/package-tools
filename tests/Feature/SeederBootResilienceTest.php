<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageToolsServiceProvider;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederManager;

/**
 * A package shipping a malformed seeder file must NOT crash app boot on
 * every request — the broken bundle is logged and skipped, healthy
 * bundles still register.
 */
final class SeederBootResilienceTest extends TestCase
{
    private string $sandbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandbox = sys_get_temp_dir() . '/seed-boot-resilience-' . uniqid();
        File::ensureDirectoryExists($this->sandbox);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sandbox);

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [PackageToolsServiceProvider::class];
    }

    public function test_a_broken_seeder_file_does_not_crash_boot(): void
    {
        // A seeder source file that throws when required (top-level throw)
        // makes discovery raise SeederException::discoveryFailed().
        File::put(
            $this->sandbox . '/BrokenSeeder.php',
            "<?php\n\nnamespace Broken;\n\nthrow new \\RuntimeException('boom at require time');\n",
        );

        $package = new Package;
        $package->name('acme/blog');
        $package->basePath = sys_get_temp_dir();
        $package->discoverPackageSeedersIn($this->sandbox);

        // Before the fix this threw out of boot; now it is caught + logged.
        $package->bootPackageAutoSeeders();

        // Nothing registered (the only bundle was broken), and we got here.
        $this->assertTrue($this->app->make(SeederManager::class)->registry()->isEmpty());
    }

    public function test_a_broken_bundle_does_not_stop_healthy_ones(): void
    {
        File::put(
            $this->sandbox . '/BrokenSeeder.php',
            "<?php\n\nnamespace Broken;\n\nthrow new \\RuntimeException('boom');\n",
        );

        $package = new Package;
        $package->name('acme/blog');
        $package->basePath = sys_get_temp_dir();
        // broken discovery bundle + a healthy explicit bundle
        $package->discoverPackageSeedersIn($this->sandbox);
        $package->hasPackageSeeders('acme/healthy', [HealthyBootSeeder::class]);

        $package->bootPackageAutoSeeders();

        // The healthy bundle survived the broken one.
        $registry = $this->app->make(SeederManager::class)->registry();
        $this->assertNotNull($registry->get('acme/healthy'));
    }
}

final class HealthyBootSeeder extends Seeder {}
