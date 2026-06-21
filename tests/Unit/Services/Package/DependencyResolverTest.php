<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Services\Package;

use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\Package\Tools\Services\Package\DependencyResolver;

final class DependencyResolverTest extends TestCase
{
    private DependencyResolver $resolver;

    private string $tmpRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new DependencyResolver;
        $this->tmpRoot = sys_get_temp_dir() . '/laranail-deps-' . bin2hex(random_bytes(4));
        mkdir($this->tmpRoot, 0o755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpRoot . '/{,.}*', GLOB_BRACE) ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($this->tmpRoot . '/vendor');
        @rmdir($this->tmpRoot);
        parent::tearDown();
    }

    public function test_resolve_returns_empty_when_composer_missing(): void
    {
        $this->assertSame([], $this->resolver->resolve($this->tmpRoot));
    }

    public function test_resolve_classifies_dependency_types(): void
    {
        file_put_contents($this->tmpRoot . '/composer.json', json_encode([
            'require' => [
                'php' => '^8.3',
                'ext-json' => '*',
                'illuminate/support' => '^11.0',
            ],
            'require-dev' => [
                'pestphp/pest' => '^3.0',
            ],
        ]));

        $resolved = $this->resolver->resolve($this->tmpRoot);

        $this->assertSame('platform', $resolved['runtime']['php']['type']);
        $this->assertSame('extension', $resolved['runtime']['ext-json']['type']);
        $this->assertSame('library', $resolved['runtime']['illuminate/support']['type']);
        $this->assertSame('library', $resolved['development']['pestphp/pest']['type']);
    }

    public function test_resolve_preserves_version_constraints(): void
    {
        file_put_contents($this->tmpRoot . '/composer.json', json_encode([
            'require' => ['illuminate/support' => '^11.0'],
        ]));

        $resolved = $this->resolver->resolve($this->tmpRoot);

        $this->assertSame('^11.0', $resolved['runtime']['illuminate/support']['version']);
        $this->assertSame('^11.0', $resolved['runtime']['illuminate/support']['resolved_version']);
    }

    public function test_resolve_handles_missing_require_sections(): void
    {
        file_put_contents($this->tmpRoot . '/composer.json', '{"name":"acme/pkg"}');

        $resolved = $this->resolver->resolve($this->tmpRoot);

        $this->assertSame([], $resolved['runtime']);
        $this->assertSame([], $resolved['development']);
    }

    public function test_dependencies_satisfied_requires_lock_and_vendor(): void
    {
        $this->assertFalse($this->resolver->areDependenciesSatisfied($this->tmpRoot));

        file_put_contents($this->tmpRoot . '/composer.lock', '{}');
        $this->assertFalse($this->resolver->areDependenciesSatisfied($this->tmpRoot));

        mkdir($this->tmpRoot . '/vendor', 0o755, true);
        $this->assertTrue($this->resolver->areDependenciesSatisfied($this->tmpRoot));
    }
}
