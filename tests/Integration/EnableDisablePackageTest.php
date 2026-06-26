<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Integration;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Package\Tools\Concerns\Package\ManagesComposer;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

/**
 * EnableDisablePackageTest - Integration tests for enable/disable functionality
 *
 * Tests the package enable/disable system including:
 * - Package status detection
 * - Enabling disabled packages
 * - Disabling enabled packages
 * - Composer.json manipulation
 * - Error handling
 */
class EnableDisablePackageTest extends TestCase
{
    use ManagesComposer;

    protected string $testComposerPath;

    protected array $originalComposer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testComposerPath = base_path('composer.json');

        // Backup original composer.json
        if (File::exists($this->testComposerPath)) {
            $this->originalComposer = json_decode(File::get($this->testComposerPath), true);
        }
    }

    protected function tearDown(): void
    {
        // Restore original composer.json
        if (isset($this->originalComposer)) {
            File::put(
                $this->testComposerPath,
                json_encode($this->originalComposer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
        }

        parent::tearDown();
    }

    #[Test]
    public function it_can_detect_package_not_found_status(): void
    {
        $status = $this->getPackageStatus('nonexistent', 'package');

        $this->assertEquals('not_found', $status);
    }

    #[Test]
    public function it_can_detect_enabled_package_status(): void
    {
        // Add a test package to composer.json
        $this->addTestPackageToComposer('test-vendor', 'test-package', '*@dev');

        $status = $this->getPackageStatus('test-vendor', 'test-package');

        $this->assertEquals('enabled', $status);
    }

    #[Test]
    public function it_can_detect_disabled_package_status(): void
    {
        // Add and then disable a package
        $this->addTestPackageToComposer('test-vendor', 'disabled-package', '*@dev');
        $this->disablePackage('test-vendor', 'disabled-package');

        $status = $this->getPackageStatus('test-vendor', 'disabled-package');

        $this->assertEquals('disabled', $status);
    }

    #[Test]
    public function it_can_disable_an_enabled_package(): void
    {
        $this->addTestPackageToComposer('test-vendor', 'active-package', '*@dev');

        $result = $this->disablePackage('test-vendor', 'active-package');

        $this->assertTrue($result);

        // Verify package is commented out in composer.json
        $composerContent = File::get($this->testComposerPath);
        $this->assertStringContainsString('// "test-vendor/active-package"', $composerContent);
    }

    #[Test]
    public function it_can_enable_a_disabled_package(): void
    {
        // Add, disable, then enable
        $this->addTestPackageToComposer('test-vendor', 'inactive-package', '*@dev');
        $this->disablePackage('test-vendor', 'inactive-package');

        $result = $this->enablePackage('test-vendor', 'inactive-package');

        $this->assertTrue($result);

        // Verify package is no longer commented out
        $composerContent = File::get($this->testComposerPath);
        $this->assertStringNotContainsString('// "test-vendor/inactive-package"', $composerContent);
        $this->assertStringContainsString('"test-vendor/inactive-package"', $composerContent);
    }

    #[Test]
    public function it_handles_disabling_nonexistent_package_gracefully(): void
    {
        $result = $this->disablePackage('fake-vendor', 'fake-package');

        $this->assertFalse($result);
    }

    #[Test]
    public function it_handles_enabling_nonexistent_package_gracefully(): void
    {
        $result = $this->enablePackage('fake-vendor', 'fake-package');

        $this->assertFalse($result);
    }

    #[Test]
    public function it_maintains_json_structure_after_disable(): void
    {
        $this->addTestPackageToComposer('test-vendor', 'structure-test', '*@dev');

        json_decode(File::get($this->testComposerPath), true);

        $this->disablePackage('test-vendor', 'structure-test');

        // Read and parse, ignoring comments
        $afterContent = File::get($this->testComposerPath);
        $afterContent = preg_replace('/\/\/[^\n]*\n/', "\n", $afterContent);
        $afterJson = json_decode((string) $afterContent, true);

        // Verify JSON is still valid
        $this->assertNotNull($afterJson);
        $this->assertIsArray($afterJson);
    }

    #[Test]
    public function it_can_toggle_package_multiple_times(): void
    {
        $this->addTestPackageToComposer('test-vendor', 'toggle-package', '*@dev');

        // Disable
        $this->disablePackage('test-vendor', 'toggle-package');
        $this->assertEquals('disabled', $this->getPackageStatus('test-vendor', 'toggle-package'));

        // Enable
        $this->enablePackage('test-vendor', 'toggle-package');
        $this->assertEquals('enabled', $this->getPackageStatus('test-vendor', 'toggle-package'));

        // Disable again
        $this->disablePackage('test-vendor', 'toggle-package');
        $this->assertEquals('disabled', $this->getPackageStatus('test-vendor', 'toggle-package'));

        // Enable again
        $this->enablePackage('test-vendor', 'toggle-package');
        $this->assertEquals('enabled', $this->getPackageStatus('test-vendor', 'toggle-package'));
    }

    #[Test]
    public function it_preserves_package_version_when_disabling(): void
    {
        $version = '^2.1.5';
        $this->addTestPackageToComposer('test-vendor', 'versioned-package', $version);

        $this->disablePackage('test-vendor', 'versioned-package');

        $composerContent = File::get($this->testComposerPath);
        $this->assertStringContainsString($version, $composerContent);
    }

    #[Test]
    public function it_handles_packages_with_special_characters_in_name(): void
    {
        // Package names with hyphens, numbers
        $this->addTestPackageToComposer('test-vendor-123', 'my-package-v2', '*@dev');

        $result = $this->disablePackage('test-vendor-123', 'my-package-v2');

        $this->assertTrue($result);
        $this->assertEquals('disabled', $this->getPackageStatus('test-vendor-123', 'my-package-v2'));
    }

    // 4 Artisan-command tests removed in Phase 8b — they invoked
    // packager:enable / packager:disable / packager:status which are
    // Scaffolder commands (live in laranail/package-scaffolder, not
    // package-tools). The ManagesComposer trait tests above exercise
    // the underlying composer-manipulation behaviour directly. The
    // Artisan-command surface is tested in the scaffolder repo.

    // ========================================================================
    // Helper Methods
    // ========================================================================

    /**
     * Add a test package to composer.json
     */
    protected function addTestPackageToComposer(string $vendor, string $package, string $version): void
    {
        $composer = json_decode(File::get($this->testComposerPath), true);

        if (! isset($composer['require'])) {
            $composer['require'] = [];
        }

        $composer['require']["{$vendor}/{$package}"] = $version;

        File::put(
            $this->testComposerPath,
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }
}
