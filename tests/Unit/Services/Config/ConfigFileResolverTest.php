<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Services\Config;

use Orchestra\Testbench\TestCase;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Exceptions\InvalidPath;
use Simtabi\Laranail\Package\Tools\Services\Config\ConfigFileResolver;
use Simtabi\Laranail\Package\Tools\Support\PathResolver;

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

    public function test_folder_to_key_converts_nested_path_to_dotted_key(): void
    {
        $this->assertSame('api.v1.limits', $this->resolver->folderToKey('api/v1/limits'));
    }

    public function test_folder_to_key_returns_single_segment_unchanged(): void
    {
        $this->assertSame('foo', $this->resolver->folderToKey('foo'));
    }

    public function test_folder_to_key_normalizes_backslashes_and_trims_slashes(): void
    {
        $this->assertSame('admin.panel', $this->resolver->folderToKey('admin\\panel'));
        $this->assertSame('admin.panel', $this->resolver->folderToKey('/admin/panel/'));
    }

    public function test_folder_to_key_rejects_path_traversal(): void
    {
        $this->expectException(RuntimeException::class);

        $this->resolver->folderToKey('admin/../../etc/passwd');
    }

    public function test_get_all_nested_recursively_lists_php_files_relative_to_config(): void
    {
        mkdir($this->basePath . '/config/admin', 0o755, true);
        mkdir($this->basePath . '/config/api/v1', 0o755, true);

        file_put_contents($this->basePath . '/config/flat.php', '<?php return [];');
        file_put_contents($this->basePath . '/config/admin/panel.php', '<?php return [];');
        file_put_contents($this->basePath . '/config/api/v1/limits.php', '<?php return [];');
        // Non-PHP files are ignored.
        file_put_contents($this->basePath . '/config/admin/notes.txt', 'ignore me');

        $this->assertSame(
            ['admin/panel.php', 'api/v1/limits.php', 'flat.php'],
            $this->resolver->getAllNested()
        );
    }

    public function test_get_all_nested_scopes_to_a_subfolder(): void
    {
        mkdir($this->basePath . '/config/admin', 0o755, true);
        mkdir($this->basePath . '/config/api/v1', 0o755, true);

        file_put_contents($this->basePath . '/config/admin/panel.php', '<?php return [];');
        file_put_contents($this->basePath . '/config/api/v1/limits.php', '<?php return [];');

        $this->assertSame(
            ['api/v1/limits.php'],
            $this->resolver->getAllNested('api')
        );
    }

    public function test_get_all_nested_returns_empty_for_missing_dir(): void
    {
        $this->assertSame([], $this->resolver->getAllNested('does-not-exist'));
    }

    public function test_get_all_nested_rejects_path_traversal(): void
    {
        $this->expectException(RuntimeException::class);

        $this->resolver->getAllNested('../../etc');
    }

    public function test_load_returns_a_single_nested_config_array(): void
    {
        mkdir($this->basePath . '/config/admin', 0o755, true);
        file_put_contents($this->basePath . '/config/admin/panel.php', '<?php return ["title" => "Admin"];');

        $this->assertSame(['title' => 'Admin'], $this->resolver->load('panel', 'admin'));
    }

    public function test_load_throws_when_file_is_missing(): void
    {
        $this->expectException(InvalidPath::class);

        $this->resolver->load('nope', 'admin');
    }

    public function test_load_throws_when_file_does_not_return_an_array(): void
    {
        file_put_contents($this->basePath . '/config/scalar.php', '<?php return 42;');

        $this->expectException(InvalidPath::class);

        $this->resolver->load('scalar');
    }

    public function test_load_all_returns_files_keyed_by_dotted_folder_key(): void
    {
        mkdir($this->basePath . '/config/admin', 0o755, true);
        mkdir($this->basePath . '/config/api/v1', 0o755, true);
        file_put_contents($this->basePath . '/config/flat.php', '<?php return ["a" => 1];');
        file_put_contents($this->basePath . '/config/admin/panel.php', '<?php return ["title" => "Admin"];');
        file_put_contents($this->basePath . '/config/api/v1/limits.php', '<?php return ["rate" => 60];');

        $this->assertSame(
            [
                'admin.panel' => ['title' => 'Admin'],
                'api.v1.limits' => ['rate' => 60],
                'flat' => ['a' => 1],
            ],
            $this->resolver->loadAll(),
        );
    }

    public function test_load_all_non_recursive_returns_only_top_level(): void
    {
        mkdir($this->basePath . '/config/admin', 0o755, true);
        file_put_contents($this->basePath . '/config/flat.php', '<?php return ["a" => 1];');
        file_put_contents($this->basePath . '/config/admin/panel.php', '<?php return ["title" => "Admin"];');

        $this->assertSame(['flat' => ['a' => 1]], $this->resolver->loadAll('', false));
    }

    public function test_load_all_scopes_to_a_subfolder(): void
    {
        mkdir($this->basePath . '/config/api/v1', 0o755, true);
        file_put_contents($this->basePath . '/config/api/v1/limits.php', '<?php return ["rate" => 60];');

        $this->assertSame(['api.v1.limits' => ['rate' => 60]], $this->resolver->loadAll('api'));
    }

    public function test_load_all_returns_empty_for_missing_folder(): void
    {
        $this->assertSame([], $this->resolver->loadAll('does-not-exist'));
    }

    public function test_load_all_returns_raw_data_without_processing_namespace_key(): void
    {
        mkdir($this->basePath . '/config/custom', 0o755, true);
        file_put_contents(
            $this->basePath . '/config/custom/thing.php',
            '<?php return ["__namespace" => "acme.custom", "enabled" => true];',
        );

        $this->assertSame(
            ['custom.thing' => ['__namespace' => 'acme.custom', 'enabled' => true]],
            $this->resolver->loadAll('custom'),
        );
    }
}
