<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit;

use Closure;
use Error;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Package\Tools\Exceptions\InvalidPackage;
use Simtabi\Laranail\Package\Tools\Exceptions\InvalidPath;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

/**
 * PackageServiceProvider tests
 *
 * Tests the abstract service provider that packages extend
 */
class PackageServiceProviderTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_is_a_laravel_service_provider(): void
    {
        $provider = $this->createConcreteProvider();

        $this->assertInstanceOf(ServiceProvider::class, $provider);
    }

    #[Test]
    public function it_requires_configure_package_implementation(): void
    {
        $this->expectException(Error::class);

        // Cannot instantiate abstract class
        new PackageServiceProvider(Mockery::mock(Application::class));
    }

    #[Test]
    public function it_throws_exception_when_package_name_is_missing(): void
    {
        $this->expectException(InvalidPackage::class);
        $this->expectExceptionMessage('required');

        $provider = $this->createConcreteProvider(function ($package): void {
            // Don't set name - should throw exception
        });

        $provider->register();
    }

    #[Test]
    public function it_creates_new_package_instance_on_register(): void
    {
        $packageInstance = null;

        $provider = $this->createConcreteProvider(function ($package) use (&$packageInstance): void {
            $packageInstance = $package;
            $package->setName('test-vendor/test-package');
        });

        $provider->register();

        $this->assertInstanceOf(Package::class, $packageInstance);
        $this->assertSame('test-package', $packageInstance->name);
    }

    #[Test]
    public function it_calls_lifecycle_hooks_in_correct_order(): void
    {
        $callOrder = [];

        $provider = $this->createConcreteProvider(
            function ($package) use (&$callOrder): void {
                $callOrder[] = 'configure';
                $package->setName('test-vendor/test-package');
            },
            function () use (&$callOrder): void {
                $callOrder[] = 'registering';
            },
            function () use (&$callOrder): void {
                $callOrder[] = 'registered';
            }
        );

        $provider->register();

        $this->assertSame(['registering', 'configure', 'registered'], $callOrder);
    }

    #[Test]
    public function it_sets_package_base_path_automatically(): void
    {
        $packageInstance = null;

        $provider = $this->createConcreteProvider(function ($package) use (&$packageInstance): void {
            $packageInstance = $package;
            $package->setName('test-vendor/test-package');
        });

        $provider->register();

        $this->assertNotEmpty($packageInstance->basePath);
        $this->assertIsString($packageInstance->basePath);
    }

    #[Test]
    public function it_can_override_new_package_method(): void
    {
        $customPackage = new Package;
        $customPackage->setName('test-vendor/custom-package');

        $provider = new class(Mockery::mock(Application::class)) extends PackageServiceProvider
        {
            public $customPackage;

            public function configurePackage(Package $package): void
            {
                $package->setName('test-vendor/test-package');
            }

            public function newPackage(): Package
            {
                return $this->customPackage;
            }
        };

        $provider->customPackage = $customPackage;
        $provider->register();

        $this->assertSame('test-package', $provider->package->name);
    }

    #[Test]
    public function it_handles_providers_in_subdirectory(): void
    {
        $provider = $this->createConcreteProvider(function ($package): void {
            $package->setName('test-vendor/test-package');
        });

        $provider->register();

        $basePath = $provider->package->basePath;

        // Should detect and move up from Providers directory
        $this->assertIsString($basePath);
        $this->assertNotEmpty($basePath);
    }

    #[Test]
    public function it_throws_exception_when_name_is_empty_string(): void
    {
        $this->expectException(InvalidPackage::class);
        $this->expectExceptionMessage('cannot be empty');

        $provider = $this->createConcreteProvider(function ($package): void {
            $package->setName('');
        });

        $provider->register();
    }

    #[Test]
    public function it_throws_exception_when_basepath_is_empty(): void
    {
        $this->expectException(InvalidPath::class);
        $this->expectExceptionMessage('cannot be empty');

        // This will throw during configurePackage when setPathFromBase('') is called
        $provider = new class(Mockery::mock(Application::class)) extends PackageServiceProvider
        {
            public function configurePackage(Package $package): void
            {
                $package->setName('test-vendor/test-package');
                // Intentionally set empty basePath - will throw exception
                $package->setPathFrom('');
            }
        };

        $provider->register();
    }

    #[Test]
    public function it_validates_name_before_registration(): void
    {
        $this->expectException(InvalidPackage::class);

        $provider = $this->createConcreteProvider(function ($package): void {
            // Don't set name - should throw exception
        });

        $provider->register();
    }

    #[Test]
    public function it_validates_basepath_before_registration(): void
    {
        $provider = $this->createConcreteProvider(function ($package): void {
            $package->setName('test-vendor/test-package');
        });

        // BasePath is set automatically from getPackageBaseDir()
        // So this should work, but let's verify it's not empty
        $provider->register();

        $this->assertNotEmpty($provider->package->basePath);
    }

    /**
     * Create a concrete implementation of the abstract provider for testing
     */
    private function createConcreteProvider(
        ?Closure $configureCallback = null,
        ?Closure $registeringCallback = null,
        ?Closure $registeredCallback = null
    ): PackageServiceProvider {
        $app = Mockery::mock(Application::class);
        $app->shouldReceive('runningInConsole')->andReturn(false);

        return new class($app, $configureCallback, $registeringCallback, $registeredCallback) extends PackageServiceProvider
        {
            public function __construct(
                $app,
                private readonly ?Closure $configureCallback = null,
                private readonly ?Closure $registeringCallback = null,
                private readonly ?Closure $registeredCallback = null
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
        };
    }
}
