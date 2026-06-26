<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Support\PathResolver;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

/**
 * PathResolverSecurityTest - Test path security enhancements
 */
class PathResolverSecurityTest extends TestCase
{
    #[Test]
    public function it_validates_empty_paths(): void
    {
        // Empty paths should be allowed
        PathResolver::validatePathSecurity('');

        $this->assertTrue(true); // No exception thrown
    }

    #[Test]
    public function it_rejects_absolute_unix_paths(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('starts with a slash (absolute path)');

        PathResolver::validatePathSecurity('/etc/passwd');
    }

    #[Test]
    public function it_rejects_absolute_windows_paths(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('starts with a slash (absolute path)');

        PathResolver::validatePathSecurity('\Windows\System32');
    }

    #[Test]
    public function it_rejects_windows_drive_letters(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('contains a Windows drive letter');

        PathResolver::validatePathSecurity('C:/Windows/System32');
    }

    #[Test]
    public function it_rejects_path_traversal(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('contains parent directory references (..)');

        PathResolver::validatePathSecurity('../../etc/passwd');
    }

    #[Test]
    public function it_rejects_null_bytes(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('contains null bytes');

        PathResolver::validatePathSecurity("config/app.php\0malicious");
    }

    #[Test]
    public function it_rejects_url_schemes(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('contains URL scheme');

        PathResolver::validatePathSecurity('http://example.com/malicious');
    }

    #[Test]
    public function it_allows_valid_relative_paths(): void
    {
        // These should all pass without exception
        PathResolver::validatePathSecurity('config/app.php');
        PathResolver::validatePathSecurity('resources/views/welcome.blade.php');
        PathResolver::validatePathSecurity('database/migrations');
        PathResolver::validatePathSecurity('src/Models/User.php');

        $this->assertTrue(true);
    }

    #[Test]
    public function it_detects_module_root_from_src_directory(): void
    {
        // Create a mock class file path
        $classFile = '/var/www/packages/blog/src/Providers/BlogServiceProvider.php';

        $moduleRoot = PathResolver::detectModuleRoot($classFile);

        $this->assertEquals('/var/www/packages/blog', $moduleRoot);
    }

    #[Test]
    public function it_throws_exception_when_src_not_found(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not detect module root');

        PathResolver::detectModuleRoot('/var/www/some/random/path/File.php');
    }

    #[Test]
    public function it_handles_deeply_nested_src_directories(): void
    {
        $classFile = '/var/www/platform/packages/blog/src/Http/Controllers/BlogController.php';

        $moduleRoot = PathResolver::detectModuleRoot($classFile);

        $this->assertEquals('/var/www/platform/packages/blog', $moduleRoot);
    }

    #[Test]
    public function it_normalizes_windows_paths_in_module_root(): void
    {
        if (DIRECTORY_SEPARATOR !== '\\') {
            $this->markTestSkipped('This test only runs on Windows');
        }

        $classFile = 'C:\www\packages\blog\src\Providers\BlogServiceProvider.php';

        $moduleRoot = PathResolver::detectModuleRoot($classFile);

        $this->assertEquals('C:\www\packages\blog', $moduleRoot);
    }
}
