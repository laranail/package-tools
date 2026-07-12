<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Integration;

use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\Package\Tools\Support\FluentPackageHelper;

/**
 * FluentHelperIntegrationTest - Integration tests for fluent helper API
 *
 * Tests the fluent helper in a Laravel environment
 */
class FluentHelperIntegrationTest extends TestCase
{
    protected FluentPackageHelper $helper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->helper = new FluentPackageHelper(
            vendor: 'test-vendor',
            package: 'test-package',
            configNamespace: 'test-package',
            viewNamespace: 'test-vendor/test-package',
            translationNamespace: 'test-vendor/test-package',
            routePrefix: 'packages.test-package'
        );

        // Set up test config
        config(['test-package.demo_key' => 'demo_value']);
        config(['test-package.enabled' => true]);
    }

    public function test_config_method_retrieves_values_from_laravel_config(): void
    {
        $value = $this->helper->config('demo_key');

        $this->assertEquals('demo_value', $value);
    }

    public function test_config_method_returns_default_when_key_not_found(): void
    {
        $value = $this->helper->config('non_existent_key', 'default_value');

        $this->assertEquals('default_value', $value);
    }

    public function test_config_method_returns_entire_config_when_no_key_provided(): void
    {
        $config = $this->helper->config();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('demo_key', $config);
        $this->assertEquals('demo_value', $config['demo_key']);
    }

    public function test_enabled_method_checks_config_enabled_key(): void
    {
        $this->assertTrue($this->helper->enabled());

        config(['test-package.enabled' => false]);
        $this->assertFalse($this->helper->enabled());
    }

    public function test_enabled_method_defaults_to_true_when_not_set(): void
    {
        config(['test-package' => []]);  // Clear config

        $this->assertTrue($this->helper->enabled());
    }

    public function test_route_method_generates_correct_route_name(): void
    {
        // Define a test route
        app('router')->get('/test-route', fn (): string => 'test')->name('packages.test-package.test');

        $url = $this->helper->route('test');

        $this->assertStringContainsString('/test-route', $url);
    }

    public function test_route_method_accepts_parameters(): void
    {
        app('router')->get('/test-route/{id}', fn ($id) => $id)->name('packages.test-package.show');

        $url = $this->helper->route('show', ['id' => 123]);

        $this->assertStringContainsString('123', $url);
    }

    public function test_asset_method_builds_correct_asset_path(): void
    {
        $assetPath = $this->helper->asset('logo.png');

        $this->assertStringContainsString('vendor/test-package/logo.png', $assetPath);
    }

    public function test_namespace_method_returns_correct_namespaces(): void
    {
        $this->assertEquals('test-vendor/test-package', $this->helper->namespace('view'));
        $this->assertEquals('test-vendor/test-package', $this->helper->namespace('translation'));
        $this->assertEquals('test-vendor/test-package', $this->helper->namespace('trans'));
        $this->assertEquals('test-vendor/test-package', $this->helper->namespace('lang'));
        $this->assertEquals('test-package', $this->helper->namespace('config'));
    }

    public function test_namespace_method_throws_exception_for_invalid_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown namespace type: invalid');

        $this->helper->namespace('invalid');
    }

    public function test_view_method_returns_view_instance(): void
    {
        // Create a test view (ensure fixtures dir exists first).
        $fixturesDir = __DIR__ . '/../fixtures/views';
        if (! is_dir($fixturesDir)) {
            mkdir($fixturesDir, 0o755, true);
        }
        view()->addNamespace('test-vendor/test-package', $fixturesDir);
        file_put_contents($fixturesDir . '/test.blade.php', '<p>Test view</p>');

        $view = $this->helper->view('test');

        $this->assertInstanceOf(View::class, $view);

        // Cleanup
        @unlink(__DIR__ . '/../fixtures/views/test.blade.php');
    }

    public function test_getter_methods_return_correct_values(): void
    {
        $this->assertEquals('test-vendor', $this->helper->vendor());
        $this->assertEquals('test-package', $this->helper->package());
        $this->assertEquals('packages.test-package', $this->helper->routePrefix());
        $this->assertEquals('test-vendor/test-package', $this->helper->viewNamespace());
        $this->assertEquals('test-vendor/test-package', $this->helper->translationNamespace());
        $this->assertEquals('test-package', $this->helper->configNamespace());
    }

    public function test_helper_works_with_vendor_dot_package_config_style(): void
    {
        $helper = new FluentPackageHelper(
            vendor: 'test-vendor',
            package: 'test-package',
            configNamespace: 'test-vendor.test-package',
            viewNamespace: 'test-vendor/test-package',
            translationNamespace: 'test-vendor/test-package',
            routePrefix: 'packages.test-package'
        );

        config(['test-vendor.test-package.key' => 'value']);

        $this->assertEquals('value', $helper->config('key'));
        $this->assertEquals('test-vendor.test-package', $helper->configNamespace());
    }

    public function test_helper_handles_nested_config_keys(): void
    {
        config([
            'test-package.nested.deep.key' => 'nested_value',
        ]);

        $value = $this->helper->config('nested.deep.key');

        $this->assertEquals('nested_value', $value);
    }

    public function test_multiple_helper_instances_do_not_interfere(): void
    {
        $helper1 = new FluentPackageHelper(
            vendor: 'vendor1',
            package: 'package1',
            configNamespace: 'package1',
            viewNamespace: 'vendor1/package1',
            translationNamespace: 'vendor1/package1',
            routePrefix: 'packages.package1'
        );

        $helper2 = new FluentPackageHelper(
            vendor: 'vendor2',
            package: 'package2',
            configNamespace: 'package2',
            viewNamespace: 'vendor2/package2',
            translationNamespace: 'vendor2/package2',
            routePrefix: 'packages.package2'
        );

        $this->assertEquals('vendor1', $helper1->vendor());
        $this->assertEquals('vendor2', $helper2->vendor());
        $this->assertEquals('package1', $helper1->package());
        $this->assertEquals('package2', $helper2->package());
    }
}
