<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Tests\Unit\Exceptions;

use Exception;
use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\PackageTools\Exceptions\InvalidPath;

/**
 * InvalidPathTest - Comprehensive tests for InvalidPath exception
 */
class InvalidPathTest extends TestCase
{
    public function test_it_creates_base_path_not_set_exception(): void
    {
        $exception = InvalidPath::basePathNotSet();

        $this->assertInstanceOf(InvalidPath::class, $exception);
        $this->assertStringContainsString('base path has not been set', $exception->getMessage());
        $this->assertStringContainsString('setPathFrom', $exception->getMessage());
    }

    public function test_it_creates_base_path_does_not_exist_exception(): void
    {
        $path = '/invalid/path';
        $exception = InvalidPath::basePathDoesNotExist($path);

        $this->assertInstanceOf(InvalidPath::class, $exception);
        $this->assertStringContainsString($path, $exception->getMessage());
        $this->assertStringContainsString('does not exist', $exception->getMessage());
    }

    public function test_it_creates_required_directory_missing_exception(): void
    {
        $directory = 'config';
        $basePath = '/var/www/package';
        $exception = InvalidPath::requiredDirectoryMissing($directory, $basePath);

        $this->assertInstanceOf(InvalidPath::class, $exception);
        $this->assertStringContainsString($directory, $exception->getMessage());
        $this->assertStringContainsString($basePath, $exception->getMessage());
        $this->assertStringContainsString('not found', $exception->getMessage());
    }

    public function test_it_creates_config_file_missing_exception(): void
    {
        $configFile = 'app.php';
        $expectedPath = '/var/www/package/config/app.php';
        $exception = InvalidPath::configFileMissing($configFile, $expectedPath);

        $this->assertInstanceOf(InvalidPath::class, $exception);
        $this->assertStringContainsString($configFile, $exception->getMessage());
        $this->assertStringContainsString($expectedPath, $exception->getMessage());
    }

    public function test_it_creates_migration_file_missing_exception(): void
    {
        $migrationFile = 'create_users_table.php';
        $expectedPath = '/var/www/package/database/migrations/create_users_table.php';
        $exception = InvalidPath::migrationFileMissing($migrationFile, $expectedPath);

        $this->assertInstanceOf(InvalidPath::class, $exception);
        $this->assertStringContainsString($migrationFile, $exception->getMessage());
        $this->assertStringContainsString($expectedPath, $exception->getMessage());
    }

    public function test_it_creates_view_directory_missing_exception(): void
    {
        $viewPath = '/var/www/package/resources/views';
        $exception = InvalidPath::viewDirectoryMissing($viewPath);

        $this->assertInstanceOf(InvalidPath::class, $exception);
        $this->assertStringContainsString($viewPath, $exception->getMessage());
        $this->assertStringContainsString('View directory', $exception->getMessage());
    }

    public function test_it_creates_translation_directory_missing_exception(): void
    {
        $translationPath = '/var/www/package/resources/lang';
        $exception = InvalidPath::translationDirectoryMissing($translationPath);

        $this->assertInstanceOf(InvalidPath::class, $exception);
        $this->assertStringContainsString($translationPath, $exception->getMessage());
        $this->assertStringContainsString('Translation directory', $exception->getMessage());
    }

    public function test_it_creates_route_file_missing_exception(): void
    {
        $routeFile = 'web.php';
        $expectedPath = '/var/www/package/routes/web.php';
        $exception = InvalidPath::routeFileMissing($routeFile, $expectedPath);

        $this->assertInstanceOf(InvalidPath::class, $exception);
        $this->assertStringContainsString($routeFile, $exception->getMessage());
        $this->assertStringContainsString($expectedPath, $exception->getMessage());
    }

    public function test_it_creates_invalid_levels_up_exception(): void
    {
        $levelsUp = 0;
        $exception = InvalidPath::invalidLevelsUp($levelsUp);

        $this->assertInstanceOf(InvalidPath::class, $exception);
        $this->assertStringContainsString('Invalid levelsUp value', $exception->getMessage());
        $this->assertStringContainsString((string) $levelsUp, $exception->getMessage());
    }

    public function test_it_creates_invalid_levels_up_exception_with_reason(): void
    {
        $levelsUp = -1;
        $reason = 'Custom reason for failure';
        $exception = InvalidPath::invalidLevelsUp($levelsUp, $reason);

        $this->assertStringContainsString($reason, $exception->getMessage());
    }

    public function test_it_creates_reached_filesystem_root_exception(): void
    {
        $startPath = '/var/www/package/src/Providers';
        $levelsUp = 10;
        $levelsAchieved = 5;
        $exception = InvalidPath::reachedFilesystemRoot($startPath, $levelsUp, $levelsAchieved);

        $this->assertInstanceOf(InvalidPath::class, $exception);
        $this->assertStringContainsString($startPath, $exception->getMessage());
        $this->assertStringContainsString((string) $levelsUp, $exception->getMessage());
        $this->assertStringContainsString((string) $levelsAchieved, $exception->getMessage());
        $this->assertStringContainsString('filesystem root', $exception->getMessage());
    }

    public function test_it_creates_invalid_package_structure_exception(): void
    {
        $missingItems = ['config', 'src', 'resources'];
        $basePath = '/var/www/package';
        $exception = InvalidPath::invalidPackageStructure($missingItems, $basePath);

        $this->assertInstanceOf(InvalidPath::class, $exception);
        $this->assertStringContainsString($basePath, $exception->getMessage());

        foreach ($missingItems as $item) {
            $this->assertStringContainsString($item, $exception->getMessage());
        }
    }

    public function test_it_creates_custom_exception(): void
    {
        $message = 'Custom error message';
        $exception = InvalidPath::custom($message);

        $this->assertInstanceOf(InvalidPath::class, $exception);
        $this->assertStringContainsString($message, $exception->getMessage());
    }

    public function test_it_creates_custom_exception_with_path(): void
    {
        $message = 'Custom error message';
        $path = '/some/path';
        $exception = InvalidPath::custom($message, $path);

        $this->assertStringContainsString($message, $exception->getMessage());
        $this->assertStringContainsString($path, $exception->getMessage());
    }

    public function test_exception_extends_base_exception(): void
    {
        $exception = InvalidPath::basePathNotSet();

        $this->assertInstanceOf(Exception::class, $exception);
    }

    public function test_exceptions_can_be_thrown_and_caught(): void
    {
        $this->expectException(InvalidPath::class);

        throw InvalidPath::basePathNotSet();
    }

    public function test_all_exception_messages_are_descriptive(): void
    {
        $exceptions = [
            InvalidPath::basePathNotSet(),
            InvalidPath::basePathDoesNotExist('/path'),
            InvalidPath::requiredDirectoryMissing('config', '/base'),
            InvalidPath::configFileMissing('app.php', '/path'),
            InvalidPath::migrationFileMissing('migration.php', '/path'),
            InvalidPath::viewDirectoryMissing('/path'),
            InvalidPath::translationDirectoryMissing('/path'),
            InvalidPath::routeFileMissing('web.php', '/path'),
            InvalidPath::invalidLevelsUp(0),
            InvalidPath::reachedFilesystemRoot('/path', 3, 2),
            InvalidPath::invalidPackageStructure(['config'], '/path'),
            InvalidPath::custom('message'),
        ];

        foreach ($exceptions as $exception) {
            $this->assertIsString($exception->getMessage());
            $this->assertNotEmpty($exception->getMessage());
            $this->assertGreaterThan(20, strlen($exception->getMessage()),
                'Exception message should be descriptive');
        }
    }

    public function test_exception_messages_provide_actionable_guidance(): void
    {
        $exception = InvalidPath::basePathNotSet();
        $message = $exception->getMessage();

        // Should tell user what to do
        $this->assertTrue(
            str_contains($message, 'Call') ||
            str_contains($message, 'Please') ||
            str_contains($message, 'verify'),
            'Exception should provide actionable guidance'
        );
    }
}
