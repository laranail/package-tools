<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User as AuthUser;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use stdClass;

/**
 * registerMorphMap() / registerMorphMapFromConfig() on the Package must
 * reach Relation::morphMap() through bootPackageDeferredHooks(). config
 * is read at boot, so per-test config lands via DefineEnvironment (which
 * runs before the providers boot).
 */
final class BootPackageMorphMapsTest extends TestCase
{
    protected function tearDown(): void
    {
        // Relation::$morphMap is process-global; keep the suite hermetic
        Relation::morphMap([], false);
        Relation::requireMorphMap(false);

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [MorphMapTestPackageProvider::class];
    }

    protected function withUserModelConfig(Application $app): void
    {
        $app['config']->set('test.user_model', MorphFixtureUserModel::class);
    }

    protected function withAuthFallbackUserModel(Application $app): void
    {
        // no test.user_model — the spec must fall back to the auth provider model
        $app['config']->set('auth.providers.users.model', MorphFixtureUserModel::class);
    }

    protected function withInvalidMapEntries(Application $app): void
    {
        $app['config']->set('test.morph_map', [
            'bad' => 'Not\\A\\Real\\Class',
            'plain' => stdClass::class, // real class but not a Model
            'extra' => MorphFixtureThingModel::class,
        ]);
    }

    public function test_static_morph_map_applies_at_boot(): void
    {
        $this->assertSame(MorphFixtureThingModel::class, Relation::getMorphedModel('thing'));
    }

    public function test_closure_morph_map_is_evaluated_lazily_at_boot(): void
    {
        $this->assertTrue(
            MorphMapTestPackageProvider::$closureEvaluated,
            'the closure map must have been evaluated by boot time',
        );
        $this->assertSame(MorphFixtureLazyModel::class, Relation::getMorphedModel('lazy'));
    }

    #[DefineEnvironment('withUserModelConfig')]
    public function test_user_model_resolves_from_the_configured_key(): void
    {
        $this->assertSame(MorphFixtureUserModel::class, Relation::getMorphedModel('user'));
    }

    #[DefineEnvironment('withAuthFallbackUserModel')]
    public function test_user_model_falls_back_to_the_auth_provider_model(): void
    {
        $this->assertSame(MorphFixtureUserModel::class, Relation::getMorphedModel('user'));
    }

    #[DefineEnvironment('withInvalidMapEntries')]
    public function test_invalid_classes_in_the_config_map_are_silently_skipped(): void
    {
        $this->assertNull(Relation::getMorphedModel('bad'));
        $this->assertNull(Relation::getMorphedModel('plain'));
    }

    #[DefineEnvironment('withInvalidMapEntries')]
    public function test_config_map_aliases_merge_with_statically_registered_ones(): void
    {
        // config-supplied alias and the static registerMorphMap() alias coexist
        $this->assertSame(MorphFixtureThingModel::class, Relation::getMorphedModel('extra'));
        $this->assertSame(MorphFixtureThingModel::class, Relation::getMorphedModel('thing'));
    }

    public function test_registration_is_non_enforcing(): void
    {
        $this->assertFalse(
            Relation::requiresMorphMap(),
            'a package must never force Relation::requireMorphMap() on the host',
        );
    }
}

final class MorphFixtureThingModel extends Model {}

final class MorphFixtureLazyModel extends Model {}

final class MorphFixtureUserModel extends AuthUser {}

final class MorphMapTestPackageProvider extends PackageServiceProvider
{
    public static bool $closureEvaluated = false;

    public function configurePackage(Package $package): void
    {
        self::$closureEvaluated = false;

        $package->setName('test/morph-maps');
        $package->basePath = sys_get_temp_dir();

        $package->registerMorphMap(['thing' => MorphFixtureThingModel::class]);

        $package->registerMorphMap(static function (): array {
            self::$closureEvaluated = true;

            return ['lazy' => MorphFixtureLazyModel::class];
        });

        $package->registerMorphMapFromConfig('test.morph_map', 'test.user_model');
    }
}
