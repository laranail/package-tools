<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Concerns\Package\HasAssetPublisher;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

/**
 * HasAssetPublisherTest - Test complete asset management system
 *
 * Covers all 20 Phase 4 features
 */
class HasAssetPublisherTest extends TestCase
{
    use HasAssetPublisher;

    protected string $name = 'test/package';

    protected string $basePath = '/var/www/test-package';

    #[Test]
    public function it_registers_basic_assets(): void
    {
        $this->publishAssets('resources/assets', 'vendor/blog');

        $registry = $this->getAssetRegistry();

        $this->assertCount(1, $registry);
        $this->assertEquals('resources/assets', $registry['resources/assets']['source']);
        $this->assertEquals('vendor/blog', $registry['resources/assets']['destination']);
    }

    #[Test]
    public function it_registers_assets_with_cleanup_flag(): void
    {
        $this->publishAssets('resources/assets', 'vendor/blog', cleanBeforePublish: true);

        $this->assertTrue($this->shouldCleanAsset('resources/assets'));
    }

    #[Test]
    public function it_publishes_module_assets_all_type(): void
    {
        $this->publishModuleAssets('all');

        $registry = $this->getAssetRegistry();

        $this->assertCount(1, $registry);
        $this->assertArrayHasKey('public', $registry);
    }

    #[Test]
    public function it_publishes_module_assets_specific_types(): void
    {
        $this->publishModuleAssets(['js', 'css']);

        $registry = $this->getAssetRegistry();

        $this->assertCount(2, $registry);
        $this->assertArrayHasKey('public/js', $registry);
        $this->assertArrayHasKey('public/css', $registry);
    }

    #[Test]
    public function it_throws_exception_for_unknown_asset_type(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown asset type');

        $this->publishModuleAssets('unknown-type');
    }

    #[Test]
    public function it_publishes_asset_group(): void
    {
        $this->publishAssetGroup('frontend', [
            'resources/js' => 'vendor/blog/js',
            'resources/css' => 'vendor/blog/css',
        ]);

        $groups = $this->getAssetGroups();

        $this->assertArrayHasKey('frontend', $groups);
        $this->assertCount(2, $groups['frontend']);
    }

    #[Test]
    public function it_publishes_multiple_asset_groups(): void
    {
        $this->publishAssetGroups([
            'frontend' => [
                'resources/js' => 'vendor/blog/js',
            ],
            'backend' => [
                'resources/admin/js' => 'vendor/blog/admin/js',
            ],
        ]);

        $groups = $this->getAssetGroups();

        $this->assertCount(2, $groups);
        $this->assertArrayHasKey('frontend', $groups);
        $this->assertArrayHasKey('backend', $groups);
    }

    #[Test]
    public function it_publishes_custom_assets(): void
    {
        $this->publishCustomAssets([
            'resources/icons' => 'vendor/blog/icons',
            'resources/themes' => 'vendor/blog/themes',
        ]);

        $registry = $this->getAssetRegistry();

        $this->assertCount(2, $registry);
    }

    #[Test]
    public function it_gets_assets_by_group(): void
    {
        $this->publishAssetGroup('frontend', [
            'resources/js' => 'vendor/blog/js',
        ]);

        $assets = $this->getAssetsByGroup('frontend');

        $this->assertCount(1, $assets);
    }

    #[Test]
    public function it_returns_empty_array_for_nonexistent_group(): void
    {
        $assets = $this->getAssetsByGroup('nonexistent');

        $this->assertEmpty($assets);
    }

    #[Test]
    public function it_filters_assets_by_type(): void
    {
        $this->publishModuleAssets('js');
        $this->publishModuleAssets('css');

        $jsAssets = $this->filterAssetsByType('js');

        $this->assertCount(1, $jsAssets);
    }

    #[Test]
    public function it_checks_asset_type_existence(): void
    {
        $this->assertTrue($this->hasAssetType('js'));
        $this->assertTrue($this->hasAssetType('css'));
        $this->assertFalse($this->hasAssetType('unknown'));
    }

    #[Test]
    public function it_gets_all_standard_asset_types(): void
    {
        $types = $this->getStandardAssetTypes();

        $this->assertContains('all', $types);
        $this->assertContains('js', $types);
        $this->assertContains('css', $types);
        $this->assertContains('images', $types);
        $this->assertContains('media', $types);
        $this->assertContains('fonts', $types);
        $this->assertContains('vendors', $types);
    }

    #[Test]
    public function it_clears_asset_registry(): void
    {
        $this->publishAssets('resources/assets', 'vendor/blog');
        $this->publishAssetGroup('frontend', [
            'resources/js' => 'vendor/blog/js',
        ]);

        $this->clearAssetRegistry();

        $this->assertEmpty($this->getAssetRegistry());
        $this->assertEmpty($this->getAssetGroups());
    }

    #[Test]
    public function it_applies_custom_tag_to_assets(): void
    {
        $this->publishAssets(
            source: 'resources/custom',
            destination: 'vendor/blog/custom',
            cleanBeforePublish: false,
            tag: 'my-custom-tag'
        );

        $registry = $this->getAssetRegistry();

        $this->assertEquals('my-custom-tag', $registry['resources/custom']['tag']);
    }

    #[Test]
    public function it_generates_correct_asset_destination(): void
    {
        $this->publishModuleAssets('js');

        $registry = $this->getAssetRegistry();

        $this->assertStringContainsString('vendor/test-package/js', $registry['public/js']['destination']);
    }

    #[Test]
    public function it_supports_all_asset_type(): void
    {
        $this->publishModuleAssets('all');

        $registry = $this->getAssetRegistry();

        $this->assertStringContainsString('vendor/test-package', $registry['public']['destination']);
    }

    #[Test]
    public function it_supports_images_asset_type(): void
    {
        $this->publishModuleAssets('images');

        $this->assertTrue($this->hasAssetType('images'));
    }

    #[Test]
    public function it_supports_vendors_asset_type(): void
    {
        $this->publishModuleAssets('vendors');

        $this->assertTrue($this->hasAssetType('vendors'));
    }

    #[Test]
    public function it_supports_fonts_asset_type(): void
    {
        $this->publishModuleAssets('fonts');

        $this->assertTrue($this->hasAssetType('fonts'));
    }

    #[Test]
    public function it_declares_an_asset_group_with_default_paths(): void
    {
        $this->declareAssetGroup('js');

        $declared = $this->getDeclaredAssetGroups();

        $this->assertArrayHasKey('js', $declared);
        $this->assertSame('public/js', $declared['js']['source']);
        $this->assertSame('vendor/test-package/js', $declared['js']['target']);
    }

    #[Test]
    public function it_declares_an_asset_group_with_config_overrides(): void
    {
        $this->declareAssetGroup('admin', ['source' => 'admin/js', 'target' => 'admin']);

        $declared = $this->getDeclaredAssetGroups();

        $this->assertSame('public/admin/js', $declared['admin']['source']);
        $this->assertSame('vendor/test-package/admin', $declared['admin']['target']);
    }

    #[Test]
    public function it_declares_standard_asset_groups(): void
    {
        $this->declareStandardAssetGroups();

        $declared = $this->getDeclaredAssetGroups();

        $this->assertCount(4, $declared);
        $this->assertArrayHasKey('js', $declared);
        $this->assertArrayHasKey('css', $declared);
        $this->assertArrayHasKey('images', $declared);
        $this->assertArrayHasKey('fonts', $declared);
        $this->assertSame('public/images', $declared['images']['source']);
        $this->assertSame('vendor/test-package/images', $declared['images']['target']);
    }

    #[Test]
    public function it_declares_a_custom_asset_group(): void
    {
        $this->declareCustomAssetGroup('themes', 'resources/themes', 'themes-dist');

        $declared = $this->getDeclaredAssetGroups();

        $this->assertSame('public/resources/themes', $declared['themes']['source']);
        $this->assertSame('vendor/test-package/themes-dist', $declared['themes']['target']);
    }

    #[Test]
    public function it_keeps_declared_groups_separate_from_keys_map_groups(): void
    {
        $this->declareAssetGroup('js');
        $this->publishAssetGroup('frontend', [
            'resources/js' => 'vendor/blog/js',
        ]);

        // Declared groups hold resolved source/target only.
        $declared = $this->getDeclaredAssetGroups();
        $this->assertArrayHasKey('js', $declared);
        $this->assertArrayNotHasKey('frontend', $declared);

        // Keys-map groups hold "group:source" keys only.
        $keysMap = $this->getAssetGroups();
        $this->assertArrayHasKey('frontend', $keysMap);
        $this->assertArrayNotHasKey('js', $keysMap);
    }

    #[Test]
    public function it_publishes_module_js_via_typed_convenience(): void
    {
        $this->publishModuleJs();

        $registry = $this->getAssetRegistry();

        $this->assertArrayHasKey('public/js', $registry);
        $this->assertSame('vendor/test-package/js', $registry['public/js']['destination']);
    }

    #[Test]
    public function it_publishes_module_css_via_typed_convenience(): void
    {
        $this->publishModuleCss();

        $this->assertArrayHasKey('public/css', $this->getAssetRegistry());
    }

    #[Test]
    public function it_publishes_module_media_via_typed_convenience(): void
    {
        $this->publishModuleMedia();

        $registry = $this->getAssetRegistry();

        $this->assertArrayHasKey('public/media', $registry);
        $this->assertSame('vendor/test-package/media', $registry['public/media']['destination']);
    }

    #[Test]
    public function it_publishes_module_vendors_via_typed_convenience(): void
    {
        $this->publishModuleVendors();

        $registry = $this->getAssetRegistry();

        $this->assertArrayHasKey('public/vendors', $registry);
        $this->assertSame('vendor/test-package/vendors', $registry['public/vendors']['destination']);
    }

    #[Test]
    public function it_publishes_all_module_assets_via_typed_convenience(): void
    {
        $this->publishAllModuleAssets();

        $registry = $this->getAssetRegistry();

        $this->assertArrayHasKey('public', $registry);
    }

    #[Test]
    public function it_passes_clean_flag_through_typed_conveniences(): void
    {
        $this->publishModuleJs(clean: true);

        $this->assertTrue($this->shouldCleanAsset('public/js'));
    }

    #[Test]
    public function it_clears_declared_asset_groups_too(): void
    {
        $this->publishAssets('resources/assets', 'vendor/blog');
        $this->publishAssetGroup('frontend', [
            'resources/js' => 'vendor/blog/js',
        ]);
        $this->declareStandardAssetGroups();

        $this->clearAssetRegistry();

        $this->assertEmpty($this->getAssetRegistry());
        $this->assertEmpty($this->getAssetGroups());
        $this->assertEmpty($this->getDeclaredAssetGroups());
    }

    #[Test]
    public function it_surfaces_registry_and_declared_groups_as_publishes_on_boot(): void
    {
        $basePath = $this->createTempDirectory('asset-boot');
        File::ensureDirectoryExists($basePath . '/public/js');

        $provider = new class($this->app, $basePath) extends PackageServiceProvider
        {
            public function __construct($app, private readonly string $tempBasePath)
            {
                parent::__construct($app);
            }

            public function configurePackage(Package $package): void
            {
                $package->setName('test-vendor/boot-package')
                    ->setPathFrom($this->tempBasePath)
                    ->hasAssets()
                    ->publishModuleJs()
                    ->declareAssetGroup('js');
            }

            public function bootAssetsForTest(): void
            {
                $this->bootPackageAssets();
            }
        };

        $provider->register();
        $provider->bootAssetsForTest();

        // Registry entry (public/js => vendor/boot-package/js) surfaces under its tag.
        $registryPublishes = ServiceProvider::pathsToPublish(null, 'assets-js');
        $this->assertContains(
            public_path('vendor/boot-package/js'),
            array_values($registryPublishes)
        );
        $this->assertContains(
            $basePath . '/public/js',
            array_keys($registryPublishes)
        );

        // Declared group (existing source dir) surfaces under "{shortName}-{name}".
        $groupPublishes = ServiceProvider::pathsToPublish(null, 'boot-package-js');
        $this->assertContains(
            public_path('vendor/boot-package/js'),
            array_values($groupPublishes)
        );
    }

    // Helper method for trait
    protected function getPackageKebabName(): string
    {
        return 'test-package';
    }
}
