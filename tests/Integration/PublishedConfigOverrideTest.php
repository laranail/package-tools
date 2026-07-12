<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Integration;

use Closure;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

/**
 * A namespaced config publishes to a NESTED path (config/vendor/package.php)
 * that Laravel never auto-loads. The register-phase bridge must load that
 * published override back into the dotted key so `vendor:publish` + edit works.
 */
class PublishedConfigOverrideTest extends TestCase
{
    private string $fixtureRoot;

    private string $publishedPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureRoot = dirname(__DIR__) . '/fixtures/nested-config-package';
        $this->publishedPath = config_path('acme/widget.php');
    }

    protected function tearDown(): void
    {
        if (File::exists($this->publishedPath)) {
            File::delete($this->publishedPath);
        }
        @rmdir(dirname($this->publishedPath));

        parent::tearDown();
    }

    #[Test]
    public function a_published_namespaced_config_override_reaches_the_dotted_key(): void
    {
        File::ensureDirectoryExists(dirname($this->publishedPath));
        File::put($this->publishedPath, "<?php return ['enabled' => false, 'extra' => 'published'];");

        $this->makeProvider(static fn (Package $package): Package => $package->hasConfigFile('widget'))->register();

        // Vendor default is enabled=true; the published override wins…
        $this->assertFalse(config('acme.widget.enabled'));
        // …and contributes its own keys.
        $this->assertSame('published', config('acme.widget.extra'));
    }

    #[Test]
    public function without_a_published_override_the_vendor_default_applies(): void
    {
        $this->makeProvider(static fn (Package $package): Package => $package->hasConfigFile('widget'))->register();

        $this->assertTrue(config('acme.widget.enabled'));
    }

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
                $package->setName('acme/widget')
                    ->setPublishTagId('acme')
                    ->setPathFrom($this->fixtureRoot);

                ($this->configure)($package);
            }
        };
    }
}
