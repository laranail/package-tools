<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;

/**
 * registerObserver() on the Package must reach Model::observe() through
 * bootPackageDeferredHooks() during the provider's boot chain.
 */
final class BootPackageObserversTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ObserverFixtureObserver::$creatingFired = false;
        ObserverFixtureObserver::$createdFired = false;
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function getPackageProviders($app): array
    {
        return [ObserverTestPackageProvider::class];
    }

    public function test_observer_listener_is_registered_for_model_events(): void
    {
        $this->assertTrue(Event::hasListeners(
            'eloquent.creating: ' . ObserverFixtureModel::class,
        ));
    }

    public function test_creating_a_model_fires_the_registered_observer(): void
    {
        Schema::create('observer_fixture_models', static function ($table): void {
            $table->increments('id');
            $table->string('label')->nullable();
        });

        ObserverFixtureModel::query()->create(['label' => 'observed']);

        $this->assertTrue(ObserverFixtureObserver::$creatingFired, 'creating() should fire on the observer');
        $this->assertTrue(ObserverFixtureObserver::$createdFired, 'created() should fire on the observer');
    }
}

final class ObserverFixtureModel extends Model
{
    public $timestamps = false;

    protected $table = 'observer_fixture_models';

    protected $guarded = [];
}

final class ObserverFixtureObserver
{
    public static bool $creatingFired = false;

    public static bool $createdFired = false;

    public function creating(): void
    {
        self::$creatingFired = true;
    }

    public function created(): void
    {
        self::$createdFired = true;
    }
}

final class ObserverTestPackageProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->setName('test/observers');
        $package->basePath = sys_get_temp_dir();

        $package->registerObserver(ObserverFixtureModel::class, ObserverFixtureObserver::class);
    }
}
