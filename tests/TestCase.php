<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Base TestCase for Package runtime tests
 *
 * Provides Laravel application context for testing Package runtime functionality.
 * Note: Package runtime tests don't require service providers since Package
 * is a base class used by other packages.
 */
abstract class TestCase extends Orchestra
{
    /**
     * Setup the test environment
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Additional setup can be added here
    }

    /**
     * Get package providers
     *
     * Package runtime doesn't have its own service provider to register.
     * Individual packages using the Package class will have their own providers.
     *
     * @param Application $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [];
    }

    /**
     * Define environment setup
     *
     * @param Application $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Setup packager configuration
        $app['config']->set('packager.packages_path', sys_get_temp_dir() . '/packager-test-packages');
        $app['config']->set('packager.naming.config_namespace_style', 'simple');
        $app['config']->set('packager.naming.enforce_vendor_prefix', true);
    }

    /**
     * Clean up the testing environment before the next test
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up any test artifacts
        $this->cleanupTestArtifacts();
    }

    /**
     * Clean up test artifacts
     */
    protected function cleanupTestArtifacts(): void
    {
        $testPath = sys_get_temp_dir() . '/packager-test-packages';

        if (is_dir($testPath)) {
            $this->deleteDirectory($testPath);
        }
    }

    /**
     * Recursively delete a directory
     */
    protected function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = array_diff(scandir($directory), ['.', '..']);

        foreach ($items as $item) {
            $path = $directory . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }

    /**
     * Create a temporary test directory
     */
    protected function createTempDirectory(?string $suffix = null): string
    {
        $path = sys_get_temp_dir() . '/packager-test-' . uniqid();

        if ($suffix) {
            $path .= '-' . $suffix;
        }

        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        return $path;
    }

    /**
     * Assert that a directory exists and contains expected files
     */
    protected function assertDirectoryHasFiles(string $directory, array $expectedFiles): void
    {
        $this->assertDirectoryExists($directory);

        foreach ($expectedFiles as $file) {
            $filePath = $directory . DIRECTORY_SEPARATOR . $file;
            $this->assertFileExists($filePath, "Expected file '{$file}' not found in directory");
        }
    }

    /**
     * Assert that a file contains a string
     */
    protected function assertFileContainsString(string $needle, string $file, string $message = ''): void
    {
        $this->assertFileExists($file);
        $content = file_get_contents($file);
        $this->assertStringContainsString($needle, $content, $message);
    }

    /**
     * Assert that a file does not contain a string
     */
    protected function assertFileNotContainsString(string $needle, string $file, string $message = ''): void
    {
        $this->assertFileExists($file);
        $content = file_get_contents($file);
        $this->assertStringNotContainsString($needle, $content, $message);
    }
}
