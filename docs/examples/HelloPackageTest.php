<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Example: an end-to-end test on top of IsolatedTestCase.
|------------------------------------------------------------------------------
| IsolatedTestCase wraps Orchestra Testbench with in-memory SQLite, a seeded
| APP_KEY, and sync/array drivers. Subclasses name their provider explicitly
| via getPackageProviders(); the base class auto-discovers nothing, so a
| missing provider fails loudly at setUp.
|
| This mirrors the library's own tests/Unit/Testing/IsolatedTestCaseTest.php:
| a concrete class (the base is abstract) using PHPUnit assertions. It boots a
| provider, runs a migration, and exercises every helper:
|   assertCommandExists, assertTableExists, assertColumnExists,
|   assertTableMissing, createTempPath.
*/

namespace Acme\Hello\Tests;

use Acme\Hello\HelloPackageServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use Simtabi\Laranail\Package\Tools\Testing\IsolatedTestCase;

final class HelloPackageTest extends IsolatedTestCase
{
    /**
     * @param Application $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [HelloPackageServiceProvider::class];
    }

    public function test_install_command_is_registered(): void
    {
        // The package registers `php artisan hello:install` via hasInstallCommand().
        $this->assertCommandExists('hello:install');
    }

    public function test_migration_creates_the_greetings_table(): void
    {
        // Stand in for the package migration so the example is self-contained.
        Schema::create('greetings', function ($table): void {
            $table->id();
            $table->string('phrase');
            $table->string('locale', 8);
        });

        $this->assertTableExists('greetings');
        $this->assertColumnExists('greetings', 'phrase');
        $this->assertColumnExists('greetings', 'locale');
        $this->assertTableMissing('widgets');
    }

    public function test_temp_path_is_writable_and_auto_cleaned(): void
    {
        $path = $this->createTempPath('hello');

        self::assertDirectoryExists($path);
        self::assertTrue(is_writable($path));

        // Anything written here is removed at tearDown.
        file_put_contents($path . '/note.txt', 'ok');
        self::assertFileExists($path . '/note.txt');
    }
}
