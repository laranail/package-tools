<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Integration;

use Closure;
use Illuminate\Foundation\Application;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

/**
 * Full package bootstrap integration test
 *
 * Tests complete package registration and boot sequence
 */
class FullPackageBootstrapTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_can_register_complete_package_with_all_resources(): void
    {
        $provider = $this->createTestProvider(function ($package): void {
            $package
                ->setName('test-vendor/test-package')
                ->hasConfigFile()
                ->hasViews()
                ->hasTranslations()
                ->hasMigrations()
                ->hasRoutes('web')
                ->hasCommands(['TestCommand']);
        });

        $provider->register();

        $this->assertInstanceOf(Package::class, $provider->package);
        $this->assertSame('test-package', $provider->package->name);
        $this->assertTrue($provider->package->hasViews);
        $this->assertTrue($provider->package->hasMigrations);
        $this->assertTrue($provider->package->hasTranslations);
        $this->assertNotEmpty($provider->package->configFileNames);
        $this->assertNotEmpty($provider->package->routeFileNames);
        $this->assertNotEmpty($provider->package->commands);
    }

    #[Test]
    public function it_calls_lifecycle_hooks_in_correct_order(): void
    {
        $callOrder = [];

        $provider = $this->createTestProvider(
            function ($package) use (&$callOrder): void {
                $callOrder[] = 'configure';
                $package->setName('test-vendor/test-package');
            },
            function () use (&$callOrder): void {
                $callOrder[] = 'registering';
            },
            function () use (&$callOrder): void {
                $callOrder[] = 'registered';
            },
            function () use (&$callOrder): void {
                $callOrder[] = 'booting';
            },
            function () use (&$callOrder): void {
                $callOrder[] = 'booted';
            }
        );

        $provider->register();
        $provider->boot();

        $this->assertSame(
            ['registering', 'configure', 'registered', 'booting', 'booted'],
            $callOrder
        );
    }

    #[Test]
    public function it_auto_detects_package_base_path(): void
    {
        $provider = $this->createTestProvider(function ($package): void {
            $package->setName('test-vendor/test-package');
        });

        $provider->register();

        $this->assertNotEmpty($provider->package->basePath);
        $this->assertIsString($provider->package->basePath);
    }

    /**
     * Create a test service provider
     */
    private function createTestProvider(
        ?Closure $configureCallback = null,
        ?Closure $registeringCallback = null,
        ?Closure $registeredCallback = null,
        ?Closure $bootingCallback = null,
        ?Closure $bootedCallback = null
    ): PackageServiceProvider {
        $app = $this->app;
        if (! $app) {
            $app = Mockery::mock(Application::class);
            $app->shouldReceive('runningInConsole')->andReturn(false);
        }

        return new class($app, $configureCallback, $registeringCallback, $registeredCallback, $bootingCallback, $bootedCallback) extends PackageServiceProvider
        {
            public function __construct(
                $app,
                private readonly ?Closure $configureCallback = null,
                private readonly ?Closure $registeringCallback = null,
                private readonly ?Closure $registeredCallback = null,
                private readonly ?Closure $bootingCallback = null,
                private readonly ?Closure $bootedCallback = null
            ) {
                parent::__construct($app);
            }

            public function configurePackage(Package $package): void
            {
                if ($this->configureCallback instanceof Closure) {
                    ($this->configureCallback)($package);
                } else {
                    $package->setName('test-vendor/default-test-package');
                }
            }

            public function registeringPackage(): void
            {
                if ($this->registeringCallback instanceof Closure) {
                    ($this->registeringCallback)();
                }
            }

            public function packageRegistered(): void
            {
                if ($this->registeredCallback instanceof Closure) {
                    ($this->registeredCallback)();
                }
            }

            public function bootingPackage(): void
            {
                if ($this->bootingCallback instanceof Closure) {
                    ($this->bootingCallback)();
                }
            }

            public function packageBooted(): void
            {
                if ($this->bootedCallback instanceof Closure) {
                    ($this->bootedCallback)();
                }
            }
        };
    }
}
