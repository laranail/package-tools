<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Tests\Unit\Services\System;

use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\PackageTools\Services\System\SystemService;

final class SystemServiceTest extends TestCase
{
    private string $tmpRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpRoot = sys_get_temp_dir() . '/laranail-system-' . bin2hex(random_bytes(4));
        mkdir($this->tmpRoot, 0o755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpRoot)) {
            foreach (scandir($this->tmpRoot) ?: [] as $entry) {
                if ($entry === '.') {
                    continue;
                }
                if ($entry === '..') {
                    continue;
                }
                @unlink($this->tmpRoot . '/' . $entry);
            }
            @rmdir($this->tmpRoot);
        }
        parent::tearDown();
    }

    public function test_get_composer_array_returns_empty_when_missing(): void
    {
        $this->app->setBasePath($this->tmpRoot);

        $svc = new SystemService($this->app);

        $this->assertSame([], $svc->getComposerArray());
    }

    public function test_get_composer_array_decodes_when_present(): void
    {
        file_put_contents(
            $this->tmpRoot . '/composer.json',
            json_encode([
                'name' => 'acme/sample',
                'require' => ['php' => '^8.3'],
            ], JSON_THROW_ON_ERROR),
        );
        $this->app->setBasePath($this->tmpRoot);

        $svc = new SystemService($this->app);
        $composer = $svc->getComposerArray();

        $this->assertSame('acme/sample', $composer['name']);
        $this->assertSame('^8.3', $composer['require']['php']);
    }

    public function test_get_packages_and_dependencies_returns_resolved_versions(): void
    {
        file_put_contents(
            $this->tmpRoot . '/composer.json',
            json_encode([
                'require' => ['vendor/a' => '^1.0'],
                'require-dev' => ['vendor/b' => '^2.0'],
            ], JSON_THROW_ON_ERROR),
        );
        $this->app->setBasePath($this->tmpRoot);

        $svc = new SystemService($this->app);
        $resolved = $svc->getPackagesAndDependencies(['vendor/a', 'vendor/b', 'vendor/missing']);

        $this->assertSame(['version' => '^1.0', 'type' => 'require'], $resolved['vendor/a']);
        $this->assertSame(['version' => '^2.0', 'type' => 'require-dev'], $resolved['vendor/b']);
        $this->assertArrayNotHasKey('vendor/missing', $resolved);
    }

    public function test_os_family_returns_known_family(): void
    {
        $svc = new SystemService($this->app);

        $this->assertContains(
            $svc->getOsFamily(),
            ['windows', 'macos', 'linux', 'bsd', 'unknown'],
        );
    }

    public function test_is_ssl_installed_honours_https_server_var(): void
    {
        $svc = new SystemService($this->app);

        $original = $_SERVER['HTTPS'] ?? null;
        try {
            $_SERVER['HTTPS'] = 'on';
            $this->assertTrue($svc->isSslInstalled());

            $_SERVER['HTTPS'] = 'off';
            unset($_SERVER['REQUEST_SCHEME'], $_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['SERVER_PORT']);
            $this->assertFalse($svc->isSslInstalled());
        } finally {
            if ($original === null) {
                unset($_SERVER['HTTPS']);
            } else {
                $_SERVER['HTTPS'] = $original;
            }
        }
    }

    public function test_is_ssl_installed_honours_x_forwarded_proto(): void
    {
        $svc = new SystemService($this->app);

        $originalHttps = $_SERVER['HTTPS'] ?? null;
        $originalProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null;
        try {
            unset($_SERVER['HTTPS']);
            $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
            $this->assertTrue($svc->isSslInstalled());
        } finally {
            if ($originalHttps !== null) {
                $_SERVER['HTTPS'] = $originalHttps;
            }
            if ($originalProto === null) {
                unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
            } else {
                $_SERVER['HTTP_X_FORWARDED_PROTO'] = $originalProto;
            }
        }
    }

    public function test_get_system_env_includes_php_and_laravel_version(): void
    {
        $svc = new SystemService($this->app);
        $env = $svc->getSystemEnv();

        $this->assertSame(PHP_VERSION, $env['php_version']);
        $this->assertNotSame('unknown', $env['laravel_version']);
    }

    public function test_get_server_env_includes_php_extensions(): void
    {
        $svc = new SystemService($this->app);
        $server = $svc->getServerEnv();

        $this->assertIsArray($server['php_extensions']);
        $this->assertNotEmpty($server['php_extensions']);
    }
}
