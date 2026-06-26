<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit;

use InvalidArgumentException;
use Simtabi\Laranail\Package\Tools\Support\FluentPackageHelper;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

class FluentPackageHelperTest extends TestCase
{
    protected FluentPackageHelper $helper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->helper = new FluentPackageHelper(
            vendor: 'acme',
            package: 'blog',
            configNamespace: 'blog',
            viewNamespace: 'acme/blog',
            translationNamespace: 'acme/blog',
            routePrefix: 'packages.blog'
        );
    }

    public function test_it_returns_vendor_name(): void
    {
        $this->assertEquals('acme', $this->helper->vendor());
    }

    public function test_it_returns_package_name(): void
    {
        $this->assertEquals('blog', $this->helper->package());
    }

    public function test_it_returns_route_prefix(): void
    {
        $this->assertEquals('packages.blog', $this->helper->routePrefix());
    }

    public function test_it_returns_view_namespace(): void
    {
        $this->assertEquals('acme/blog', $this->helper->viewNamespace());
    }

    public function test_it_returns_translation_namespace(): void
    {
        $this->assertEquals('acme/blog', $this->helper->translationNamespace());
    }

    public function test_it_returns_config_namespace(): void
    {
        $this->assertEquals('blog', $this->helper->configNamespace());
    }

    public function test_it_returns_namespace_for_view_type(): void
    {
        $this->assertEquals('acme/blog', $this->helper->namespace('view'));
    }

    public function test_it_returns_namespace_for_translation_type(): void
    {
        $this->assertEquals('acme/blog', $this->helper->namespace('translation'));
        $this->assertEquals('acme/blog', $this->helper->namespace('trans'));
        $this->assertEquals('acme/blog', $this->helper->namespace('lang'));
    }

    public function test_it_returns_namespace_for_config_type(): void
    {
        $this->assertEquals('blog', $this->helper->namespace('config'));
    }

    public function test_it_throws_exception_for_unknown_namespace_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown namespace type: invalid');

        $this->helper->namespace('invalid');
    }

    public function test_it_builds_asset_url_correctly(): void
    {
        // This would require Laravel's asset() function to be available
        // For unit testing, we're mainly testing the method signature and return type
        $result = $this->helper->asset('logo.png');

        $this->assertIsString($result);
    }

    public function test_it_is_instantiable_with_all_parameters(): void
    {
        $helper = new FluentPackageHelper(
            vendor: 'test-vendor',
            package: 'test-package',
            configNamespace: 'test.package',
            viewNamespace: 'test/package',
            translationNamespace: 'test/package',
            routePrefix: 'test.package'
        );

        $this->assertInstanceOf(FluentPackageHelper::class, $helper);
        $this->assertEquals('test-vendor', $helper->vendor());
        $this->assertEquals('test-package', $helper->package());
    }

    public function test_it_handles_vendor_package_config_namespace(): void
    {
        $helper = new FluentPackageHelper(
            vendor: 'acme',
            package: 'blog',
            configNamespace: 'acme.blog',
            viewNamespace: 'acme/blog',
            translationNamespace: 'acme/blog',
            routePrefix: 'packages.blog'
        );

        $this->assertEquals('acme.blog', $helper->configNamespace());
        $this->assertEquals('acme.blog', $helper->namespace('config'));
    }

    public function test_namespace_method_is_case_sensitive(): void
    {
        // These should work
        $this->assertEquals('acme/blog', $this->helper->namespace('view'));
        $this->assertEquals('acme/blog', $this->helper->namespace('translation'));
        $this->assertEquals('blog', $this->helper->namespace('config'));

        // This should throw exception
        $this->expectException(InvalidArgumentException::class);
        $this->helper->namespace('VIEW'); // uppercase should fail
    }

    public function test_it_accepts_multi_word_package_names(): void
    {
        $helper = new FluentPackageHelper(
            vendor: 'acme-corp',
            package: 'blog-post',
            configNamespace: 'blog-post',
            viewNamespace: 'acme-corp/blog-post',
            translationNamespace: 'acme-corp/blog-post',
            routePrefix: 'packages.blog-post'
        );

        $this->assertEquals('acme-corp', $helper->vendor());
        $this->assertEquals('blog-post', $helper->package());
    }

    public function test_all_namespace_getters_return_strings(): void
    {
        $this->assertIsString($this->helper->vendor());
        $this->assertIsString($this->helper->package());
        $this->assertIsString($this->helper->routePrefix());
        $this->assertIsString($this->helper->viewNamespace());
        $this->assertIsString($this->helper->translationNamespace());
        $this->assertIsString($this->helper->configNamespace());
    }
}
