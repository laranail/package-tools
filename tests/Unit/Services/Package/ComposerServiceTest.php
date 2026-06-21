<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Services\Package;

use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\Package\Tools\Services\Package\ComposerService;

final class ComposerServiceTest extends TestCase
{
    private ComposerService $service;

    private string $pkg;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ComposerService;
        $this->pkg = sys_get_temp_dir() . '/laranail-composer-' . bin2hex(random_bytes(4));
        mkdir($this->pkg, 0o755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->pkg . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->pkg);
        parent::tearDown();
    }

    public function test_get_composer_data_returns_null_when_missing(): void
    {
        $this->assertNull($this->service->getComposerData($this->pkg));
    }

    public function test_get_composer_data_decodes_json(): void
    {
        file_put_contents($this->pkg . '/composer.json', json_encode([
            'name' => 'acme/widget',
            'require' => ['php' => '^8.3'],
        ]));

        $data = $this->service->getComposerData($this->pkg);

        $this->assertSame('acme/widget', $data['name']);
        $this->assertSame('^8.3', $data['require']['php']);
    }

    public function test_update_composer_json_merges_recursively(): void
    {
        file_put_contents($this->pkg . '/composer.json', json_encode([
            'name' => 'acme/widget',
            'require' => ['php' => '^8.3'],
        ]));

        $ok = $this->service->updateComposerJson($this->pkg, [
            'require' => ['illuminate/support' => '^11.0'],
            'license' => 'MIT',
        ]);

        $this->assertTrue($ok);

        $data = $this->service->getComposerData($this->pkg);
        $this->assertSame('^8.3', $data['require']['php']);
        $this->assertSame('^11.0', $data['require']['illuminate/support']);
        $this->assertSame('MIT', $data['license']);
        $this->assertSame('acme/widget', $data['name']);
    }

    public function test_update_composer_json_creates_file_when_absent(): void
    {
        $ok = $this->service->updateComposerJson($this->pkg, ['name' => 'acme/new']);

        $this->assertTrue($ok);
        $this->assertSame('acme/new', $this->service->getComposerData($this->pkg)['name']);
    }
}
