<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Tests\Unit\Services\Config;

use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\PackageTools\Services\Config\ConfigFileResolver;
use Simtabi\Laranail\PackageTools\Support\PathResolver;

final class ConfigFileResolverTest extends TestCase
{
    private string $basePath;

    private ConfigFileResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->basePath = sys_get_temp_dir() . '/laranail-cfgresolver-' . bin2hex(random_bytes(4));
        mkdir($this->basePath . '/config/nested', 0o755, true);
        $this->resolver = new ConfigFileResolver($this->basePath);
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->basePath);
        parent::tearDown();
    }

    private function deleteTree(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.') {
                continue;
            }
            if ($entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->deleteTree($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    public function test_resolve_builds_config_path(): void
    {
        $expected = PathResolver::normalizePath(
            $this->basePath . '/config/app.php'
        );

        $this->assertSame($expected, $this->resolver->resolve('app'));
    }

    public function test_resolve_nested_builds_subfolder_path(): void
    {
        $expected = PathResolver::normalizePath(
            $this->basePath . '/config/modules/admin.php'
        );

        $this->assertSame($expected, $this->resolver->resolveNested('admin', 'modules'));
    }

    public function test_resolve_nested_with_empty_folder_falls_back_to_resolve(): void
    {
        $this->assertSame($this->resolver->resolve('app'), $this->resolver->resolveNested('app', ''));
        $this->assertSame($this->resolver->resolve('app'), $this->resolver->resolveNested('app', '0'));
    }

    public function test_exists_detects_present_and_absent_files(): void
    {
        file_put_contents($this->basePath . '/config/present.php', '<?php return [];');

        $this->assertTrue($this->resolver->exists('present'));
        $this->assertFalse($this->resolver->exists('absent'));
    }

    public function test_get_all_in_directory_lists_only_php_files(): void
    {
        file_put_contents($this->basePath . '/config/a.php', '<?php return [];');
        file_put_contents($this->basePath . '/config/b.php', '<?php return [];');
        file_put_contents($this->basePath . '/config/readme.txt', 'ignore me');

        $files = $this->resolver->getAllInDirectory();

        sort($files);
        $this->assertSame(['a', 'b'], $files);
    }

    public function test_get_all_in_directory_returns_empty_for_missing_dir(): void
    {
        $this->assertSame([], $this->resolver->getAllInDirectory('does-not-exist'));
    }

    public function test_can_resolve_rejects_empty_inputs(): void
    {
        $this->assertTrue($this->resolver->canResolve('app'));
        $this->assertFalse($this->resolver->canResolve(''));
        $this->assertFalse($this->resolver->canResolve('0'));
    }
}
