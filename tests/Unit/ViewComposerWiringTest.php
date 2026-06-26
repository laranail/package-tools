<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit;

use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\View;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

/**
 * Regression guard: the enhanced view composer registry, global composers,
 * creators, and dependency-injected composers must actually boot through the
 * provider (they used to be dead-wired).
 */
class ViewComposerWiringTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function provider_boot_fires_a_registered_view_composer(): void
    {
        $package = $this->newConfiguredPackage();
        $package->registerViewComposer('dashboard', function (ViewContract $view): void {
            $view->with('composed', 'yes');
        }, autoPrefix: false);

        $this->bootViewComposers($package);

        $rendered = $this->renderView('dashboard');

        $this->assertSame('yes', $rendered->getData()['composed'] ?? null);
    }

    #[Test]
    public function provider_boot_fires_a_global_view_composer(): void
    {
        $package = $this->newConfiguredPackage();
        $package->registerGlobalViewComposer(function (ViewContract $view): void {
            $view->with('global', 'fired');
        });

        $this->bootViewComposers($package);

        $rendered = $this->renderView('anything');

        $this->assertSame('fired', $rendered->getData()['global'] ?? null);
    }

    #[Test]
    public function provider_boot_fires_a_registered_view_creator(): void
    {
        $package = $this->newConfiguredPackage();
        $package->registerViewCreator('dashboard', function (ViewContract $view): void {
            $view->with('created', 'now');
        }, autoPrefix: false);

        $this->bootViewComposers($package);

        $rendered = $this->renderView('dashboard');

        $this->assertSame('now', $rendered->getData()['created'] ?? null);
    }

    #[Test]
    public function provider_boot_resolves_a_composer_with_container_dependencies(): void
    {
        $this->app->instance(ViewComposerWiringDependency::class, new ViewComposerWiringDependency('injected'));

        $package = $this->newConfiguredPackage();
        $package->registerViewComposerWithDependencies(
            'dashboard',
            ViewComposerWiringComposer::class,
            autoPrefix: false
        );

        $this->bootViewComposers($package);

        $rendered = $this->renderView('dashboard');

        $this->assertSame('injected', $rendered->getData()['dependency'] ?? null);
    }

    /**
     * Register an on-disk view path and render the given view, returning the
     * resolved view instance (with composer/creator data applied).
     */
    private function renderView(string $name): ViewContract
    {
        $dir = $this->createTempDirectory('views');
        file_put_contents($dir . '/' . $name . '.blade.php', 'content');

        View::addLocation($dir);

        $view = View::make($name);
        $view->render();

        return $view;
    }

    /**
     * Build a real Package wired to the enhanced view composer trait.
     */
    private function newConfiguredPackage(): Package
    {
        $package = new Package;
        $package->setName('test-vendor/test-package');

        return $package;
    }

    /**
     * Drive the provider's bootPackageViewComposers() against the package, the
     * same path the real boot() chain uses.
     */
    private function bootViewComposers(Package $package): void
    {
        $provider = new class($this->app) extends PackageServiceProvider
        {
            public function configurePackage(Package $package): void
            {
                $package->setName('test-vendor/test-package');
            }
        };

        $provider->package = $package;

        $boot = new ReflectionMethod($provider, 'bootPackageViewComposers');
        $boot->invoke($provider);
    }
}

class ViewComposerWiringDependency
{
    public function __construct(public string $value) {}
}

class ViewComposerWiringComposer
{
    public function __construct(private readonly ViewComposerWiringDependency $dependency) {}

    public function compose(ViewContract $view): void
    {
        $view->with('dependency', $this->dependency->value);
    }
}
