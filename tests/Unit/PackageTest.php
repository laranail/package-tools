<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\PackageTools\Exceptions\InvalidPackage;
use Simtabi\Laranail\PackageTools\Exceptions\InvalidPath;
use Simtabi\Laranail\PackageTools\Package;

/**
 * Core Package class tests
 *
 * Tests the fundamental Package class functionality including
 * naming, paths, tag generation, and fluent API.
 */
class PackageTest extends TestCase
{
    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();
        $this->package = new Package;
    }

    #[Test]
    public function it_can_set_and_get_package_name(): void
    {
        $result = $this->package->setName('test-vendor/test-package');

        $this->assertSame($this->package, $result, 'Should return self for fluent chaining');
        $this->assertSame('test-package', $this->package->name);
    }

    #[Test]
    public function it_can_set_and_get_base_path(): void
    {
        $path = '/var/www/packages/my-package';
        $result = $this->package->setPathFrom($path);

        $this->assertSame($this->package, $result, 'Should return self for fluent chaining');
        $this->assertSame($path, $this->package->basePath);
    }

    #[Test]
    public function it_generates_short_name_from_full_name(): void
    {
        $this->package->setName('vendor/my-awesome-package');

        $this->assertSame('my-awesome-package', $this->package->shortName());
    }

    #[Test]
    public function it_handles_package_name_without_vendor(): void
    {
        $this->package->setName('test-vendor/standalone-package');

        $this->assertSame('standalone-package', $this->package->shortName());
    }

    #[Test]
    public function it_has_correct_directory_constants(): void
    {
        $this->assertSame('config', Package::CONFIG_DIR);
        $this->assertSame('resources/views', Package::VIEWS_DIR);
        $this->assertSame('resources/lang', Package::LANG_DIR);
        $this->assertSame('helpers', Package::HELPERS_DIR);
        $this->assertSame('database/migrations', Package::MIGRATIONS_DIR);
        $this->assertSame('routes', Package::ROUTES_DIR);
        $this->assertSame('resources/assets', Package::ASSETS_DIR);
    }

    #[Test]
    public function it_supports_fluent_api_chaining(): void
    {
        $result = $this->package
            ->setName('test-vendor/test-package')
            ->setPathFrom('/test/path');

        $this->assertSame($this->package, $result);
        $this->assertSame('test-package', $this->package->name);
        $this->assertSame('/test/path', $this->package->basePath);
    }

    #[Test]
    public function it_can_check_if_package_has_name(): void
    {
        // Initially name is empty string (default)
        $this->assertSame('', $this->package->name);

        $this->package->setName('test-vendor/my-package');

        $this->assertNotEmpty($this->package->name);
        $this->assertSame('my-package', $this->package->name);
    }

    #[Test]
    public function it_normalizes_package_names(): void
    {
        $this->package->setName('test-vendor/MyPackage');

        // Package names should be preserved as provided
        $this->assertSame('MyPackage', $this->package->name);
    }

    #[Test]
    public function it_handles_complex_vendor_package_names(): void
    {
        $this->package->setName('my-org/sub-org/deep-package');

        $shortName = $this->package->shortName();
        $this->assertStringContainsString('package', $shortName);
    }

    #[Test]
    public function it_can_be_instantiated_multiple_times(): void
    {
        $package1 = new Package;
        $package2 = new Package;

        $package1->setName('test-vendor/package-one');
        $package2->setName('test-vendor/package-two');

        $this->assertSame('package-one', $package1->name);
        $this->assertSame('package-two', $package2->name);
        $this->assertNotSame($package1, $package2);
    }

    #[Test]
    public function it_throws_exception_when_name_is_empty_string(): void
    {
        $this->expectException(InvalidPackage::class);
        $this->expectExceptionMessage('cannot be empty');

        $this->package->setName('');
    }

    #[Test]
    public function it_throws_exception_when_name_is_whitespace_only(): void
    {
        $this->expectException(InvalidPackage::class);
        $this->expectExceptionMessage('cannot be empty');

        $this->package->setName('   ');
    }

    #[Test]
    public function it_throws_exception_when_name_has_invalid_characters(): void
    {
        $this->expectException(InvalidPackage::class);
        $this->expectExceptionMessage('Invalid package name');

        $this->package->setName('my@package#name');
    }

    #[Test]
    public function it_throws_exception_when_basepath_is_empty_string(): void
    {
        $this->expectException(InvalidPath::class);
        $this->expectExceptionMessage('cannot be empty');

        $this->package->setPathFrom('');
    }

    #[Test]
    public function it_throws_exception_when_basepath_is_whitespace_only(): void
    {
        $this->expectException(InvalidPath::class);
        $this->expectExceptionMessage('cannot be empty');

        $this->package->setPathFrom('   ');
    }

    #[Test]
    public function name_and_setname_are_aliases(): void
    {
        $package1 = new Package;
        $package2 = new Package;

        $package1->setName('test-vendor/test-package');
        $package2->setName('test-vendor/test-package');

        $this->assertSame($package1->name, $package2->name);
    }

    #[Test]
    public function name_cannot_be_empty_after_setting(): void
    {
        $this->package->setName('test-vendor/valid-package');
        $this->assertNotEmpty($this->package->name);

        $this->expectException(InvalidPackage::class);
        $this->package->setName('');
    }

    #[Test]
    public function basepath_cannot_be_empty_after_setting(): void
    {
        $this->package->setPathFrom('/valid/path');
        $this->assertNotEmpty($this->package->basePath);

        $this->expectException(InvalidPath::class);
        $this->package->setPathFrom('');
    }

    #[Test]
    public function it_validates_vendor_name_in_vendor_package_format(): void
    {
        $this->expectException(InvalidPackage::class);

        // Empty vendor name
        $this->package->setName('/package-name');
    }

    #[Test]
    public function it_validates_package_name_in_vendor_package_format(): void
    {
        $this->expectException(InvalidPackage::class);

        // Empty package name after vendor
        $this->package->setName('vendor/');
    }

    #[Test]
    public function it_validates_name_after_transformer(): void
    {
        $this->expectException(InvalidPackage::class);

        // Transformer returns empty string
        $this->package->setName('test-vendor/valid-name', fn ($n): string => '');
    }
}
