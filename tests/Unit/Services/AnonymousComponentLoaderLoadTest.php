<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Services;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Package\Tools\Services\Component\AnonymousComponentLoader;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

/**
 * Bug 3: HasEnhancedAnonymousComponents previously called the non-existent
 * loadAnonymous() on AnonymousComponentLoader. The fixed call site uses load(),
 * which registers an anonymous component path with the Blade compiler.
 */
class AnonymousComponentLoaderLoadTest extends TestCase
{
    private string $componentsDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->componentsDir = sys_get_temp_dir() . '/laranail-anon-components-' . uniqid();
        File::ensureDirectoryExists($this->componentsDir);
        File::put($this->componentsDir . '/alert.blade.php', '<div>alert</div>');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->componentsDir);

        parent::tearDown();
    }

    #[Test]
    public function load_registers_an_anonymous_component_path(): void
    {
        $loader = $this->app->make(AnonymousComponentLoader::class);

        $loader->load($this->componentsDir, 'pkg/widgets');

        // Prefix is normalized (slashes -> dashes) and recorded as loaded.
        $this->assertSame(
            [$this->componentsDir],
            array_values($loader->getLoaded()),
        );
        $this->assertArrayHasKey('pkg-widgets', $loader->getLoaded());
    }

    #[Test]
    public function load_is_a_no_op_for_a_directory_without_php_files(): void
    {
        $empty = sys_get_temp_dir() . '/laranail-anon-empty-' . uniqid();
        File::ensureDirectoryExists($empty);

        $loader = $this->app->make(AnonymousComponentLoader::class);
        $loader->load($empty, 'empty');

        $this->assertSame([], $loader->getLoaded());

        File::deleteDirectory($empty);
    }
}
