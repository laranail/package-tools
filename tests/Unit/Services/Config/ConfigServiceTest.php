<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Services\Config;

use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\Package\Tools\Services\Config\ConfigService;

final class ConfigServiceTest extends TestCase
{
    private ConfigService $service;

    private string $tmpRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ConfigService($this->app);
        $this->tmpRoot = sys_get_temp_dir() . '/laranail-cfgservice-' . bin2hex(random_bytes(4));
        mkdir($this->tmpRoot, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->tmpRoot);
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

    private function writeConfig(string $name, array $values): string
    {
        $path = $this->tmpRoot . '/' . $name;
        file_put_contents($path, '<?php return ' . var_export($values, true) . ';');

        return $path;
    }

    public function test_set_get_has_round_trip_with_dot_notation(): void
    {
        $this->service->set('foo.bar', 'baz');

        $this->assertSame('baz', $this->service->get('foo.bar'));
        $this->assertTrue($this->service->has('foo.bar'));
        $this->assertSame('default', $this->service->get('foo.missing', 'default'));
    }

    public function test_merge_missing_file_is_noop(): void
    {
        $this->service->merge($this->tmpRoot . '/nope.php', 'pkg');

        $this->assertNull($this->service->get('pkg'));
        $this->assertSame([], $this->service->getMergedConfigs());
    }

    public function test_merge_records_path_and_existing_values_take_precedence(): void
    {
        $this->service->set('pkg', ['name' => 'existing', 'extra' => 'kept']);
        $path = $this->writeConfig('pkg.php', ['name' => 'file-default', 'driver' => 'sync']);

        $this->service->merge($path, 'pkg');

        $merged = $this->service->get('pkg');
        // array_merge($config, $existing): existing values override file defaults.
        $this->assertSame('existing', $merged['name']);
        $this->assertSame('sync', $merged['driver']);
        $this->assertSame('kept', $merged['extra']);
        $this->assertSame([$path], array_values($this->service->getMergedConfigs()));
    }

    public function test_merge_ignores_file_not_returning_array(): void
    {
        $path = $this->writeConfig('scalar.php', []);
        file_put_contents($path, '<?php return 5;');

        $this->service->merge($path, 'scalarkey');

        $this->assertNull($this->service->get('scalarkey'));
        $this->assertSame([], $this->service->getMergedConfigs());
    }

    public function test_merge_global_recursively_combines_with_existing(): void
    {
        $this->service->set('services', ['stripe' => ['key' => 'existing']]);
        $path = $this->writeConfig('services.php', ['mailgun' => ['domain' => 'x']]);

        $this->service->mergeGlobal($path, 'services');

        $merged = $this->service->get('services');
        $this->assertArrayHasKey('stripe', $merged);
        $this->assertSame('x', $merged['mailgun']['domain']);
    }

    public function test_forget_removes_key(): void
    {
        $this->service->set('removable.value', 'gone');
        $this->assertTrue($this->service->has('removable.value'));

        $this->service->forget('removable.value');

        $this->assertFalse($this->service->has('removable.value'));
    }

    public function test_is_ready_and_name(): void
    {
        $this->assertTrue($this->service->isReady());
        $this->assertSame('config', $this->service->getName());
    }

    public function test_load_from_returns_raw_package_config_keyed_by_folder(): void
    {
        $base = $this->tmpRoot . '/pkg';
        mkdir($base . '/config/admin', 0o755, true);
        file_put_contents($base . '/config/admin/panel.php', '<?php return ["title" => "Admin"];');
        file_put_contents($base . '/config/widget.php', '<?php return ["enabled" => true];');

        // Returns raw arrays keyed by folder; nothing is registered in config().
        $this->assertSame(
            [
                'admin.panel' => ['title' => 'Admin'],
                'widget' => ['enabled' => true],
            ],
            $this->service->loadFrom($base),
        );
        $this->assertFalse($this->service->has('admin.panel'));
    }
}
