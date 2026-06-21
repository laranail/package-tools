<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase;
use ReflectionClass;
use Simtabi\Laranail\Package\Tools\Concerns\Package\HasFactoriesAndSeeders;

/**
 * Locks in the fix for the factory-resolver null bug:
 * `bootPackageFactories()` installs a global `guessFactoryNamesUsing`
 * resolver. For models that are NOT part of the package it must fall back
 * to Laravel's conventional `Factory::$namespace.{Model}Factory` name —
 * returning null would globally break the host app's factory resolution.
 */
final class FactoryResolverFallbackTest extends TestCase
{
    private string $factoryDir;

    /** @var callable|null */
    private mixed $originalResolver;

    protected function setUp(): void
    {
        parent::setUp();

        // Preserve the global resolver so this test does not leak state.
        $this->originalResolver = $this->readResolver();

        $this->factoryDir = sys_get_temp_dir() . '/laranail-factory-' . uniqid();
        File::makeDirectory($this->factoryDir, 0755, true);
        // Drop one real package factory so the directory branch is exercised.
        File::put($this->factoryDir . '/WidgetFactory.php', "<?php\n");
    }

    protected function tearDown(): void
    {
        $this->restoreResolver($this->originalResolver);

        if (File::isDirectory($this->factoryDir)) {
            File::deleteDirectory($this->factoryDir);
        }

        parent::tearDown();
    }

    public function test_non_package_model_falls_back_to_conventional_factory_name(): void
    {
        $package = new class
        {
            use HasFactoriesAndSeeders;

            public string $basePath = '';

            public function getPath(string $path): string
            {
                return $path;
            }
        };

        $package->loadFactoriesFrom($this->factoryDir);
        $package->bootPackageFactories();

        // A model the package knows nothing about must resolve to the
        // host app's conventional factory name, never null.
        $resolved = Factory::resolveFactoryName('App\\Models\\Post');

        $this->assertNotNull($resolved);
        $this->assertSame(Factory::$namespace . 'PostFactory', $resolved);
    }

    /** @return callable|null */
    private function readResolver(): mixed
    {
        $prop = (new ReflectionClass(Factory::class))->getProperty('factoryNameResolver');

        return $prop->getValue();
    }

    private function restoreResolver(?callable $resolver): void
    {
        $prop = (new ReflectionClass(Factory::class))->getProperty('factoryNameResolver');
        $prop->setValue(null, $resolver);
    }
}
