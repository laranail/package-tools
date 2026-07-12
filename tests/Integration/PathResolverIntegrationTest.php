<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Support\PathResolver;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

/**
 * PathResolverIntegrationTest - Integration tests for PathResolver
 *
 * Tests the self-contained PathResolver including:
 * - Cross-platform path resolution
 * - Package root detection
 * - Path normalization
 * - Validation
 */
class PathResolverIntegrationTest extends TestCase
{
    #[Test]
    public function it_can_resolve_package_root_from_provider(): void
    {
        // Create a mock provider class
        $mockProvider = new class
        {
            public function getPath(): string
            {
                return __FILE__;
            }
        };

        $root = PathResolver::packageRootFromProvider($mockProvider, 3);

        $this->assertIsString($root);
        $this->assertDirectoryExists($root);
    }

    #[Test]
    public function it_normalizes_paths_correctly(): void
    {
        $windowsPath = 'C:\Users\Test\package';
        $normalized = PathResolver::normalizePath($windowsPath);

        $this->assertEquals('C:/Users/Test/package', $normalized);
    }

    #[Test]
    public function it_detects_absolute_paths_correctly(): void
    {
        // Unix-style
        $this->assertTrue(PathResolver::isAbsolutePath('/var/www/package'));

        // Windows-style
        $this->assertTrue(PathResolver::isAbsolutePath('C:/Users/package'));
        $this->assertTrue(PathResolver::isAbsolutePath('C:\Users\package'));

        // Relative
        $this->assertFalse(PathResolver::isAbsolutePath('relative/path'));
        $this->assertFalse(PathResolver::isAbsolutePath('./relative'));
        $this->assertFalse(PathResolver::isAbsolutePath('../parent'));
    }

    #[Test]
    public function it_converts_to_absolute_paths(): void
    {
        $relativePath = 'packages/test-package';
        $basePath = '/var/www/laravel';

        $absolute = PathResolver::toAbsolutePath($relativePath, $basePath);

        $this->assertEquals('/var/www/laravel/packages/test-package', $absolute);
    }

    #[Test]
    public function it_handles_already_absolute_paths(): void
    {
        $absolutePath = '/var/www/packages/test';

        $result = PathResolver::toAbsolutePath($absolutePath, '/some/base');

        $this->assertEquals($absolutePath, $result);
    }

    #[Test]
    public function it_converts_to_relative_paths(): void
    {
        $absolutePath = '/var/www/laravel/packages/test-package';
        $basePath = '/var/www/laravel';

        $relative = PathResolver::toRelativePath($absolutePath, $basePath);

        $this->assertEquals('packages/test-package', $relative);
    }

    #[Test]
    public function it_validates_levels_up_parameter(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid levelsUp value: 0. Must be between 1 and 10.');

        PathResolver::packageRootFromProvider($this, 0);
    }

    #[Test]
    public function it_rejects_excessive_levels_up(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid levelsUp value: 11. Must be between 1 and 10.');

        PathResolver::packageRootFromProvider($this, 11);
    }

    #[Test]
    public function it_handles_dot_segments_in_paths(): void
    {
        $pathWithDots = '/var/www/./laravel/../packages/./test';
        $normalized = PathResolver::normalizePath($pathWithDots);

        // Should resolve . and .. segments
        $this->assertStringNotContainsString('/./', $normalized);
        $this->assertStringNotContainsString('/../', $normalized);
    }

    #[Test]
    public function it_preserves_trailing_slashes_when_requested(): void
    {
        $path = '/var/www/package/';
        $normalized = PathResolver::normalizePath($path);

        // By default, trailing slashes should be removed
        $this->assertEquals('/var/www/package', $normalized);
    }

    #[Test]
    public function it_works_with_string_caller(): void
    {
        $callerPath = __FILE__;

        $root = PathResolver::packageRootFromProvider($callerPath, 3);

        $this->assertIsString($root);
        $this->assertDirectoryExists($root);
    }

    #[Test]
    public function it_works_with_object_caller(): void
    {
        $root = PathResolver::packageRootFromProvider($this, 4);

        $this->assertIsString($root);
        $this->assertDirectoryExists($root);
    }

    #[Test]
    public function it_handles_windows_drive_letters(): void
    {
        $windowsPath = 'C:/Users/Test/package';

        $this->assertTrue(PathResolver::isAbsolutePath($windowsPath));
    }

    #[Test]
    public function it_handles_unc_paths(): void
    {
        $uncPath = '//server/share/package';

        $this->assertTrue(PathResolver::isAbsolutePath($uncPath));
    }

    #[Test]
    public function it_resolves_complex_relative_paths(): void
    {
        $basePath = '/var/www/laravel/packages/packager';
        $relativePath = '../../vendor/symfony';

        $absolute = PathResolver::toAbsolutePath($relativePath, $basePath);

        $this->assertEquals('/var/www/laravel/vendor/symfony', $absolute);
    }

    #[Test]
    public function it_handles_empty_paths_gracefully(): void
    {
        $normalized = PathResolver::normalizePath('');

        $this->assertEquals('', $normalized);
    }
}
