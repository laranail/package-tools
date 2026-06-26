<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Testing;

use Illuminate\Support\Facades\Schema;
use Simtabi\Laranail\Package\Tools\Testing\IsolatedTestCase;

/**
 * Concrete subclass we can actually instantiate as a test (the base
 * IsolatedTestCase is abstract).
 */
final class IsolatedTestCaseTest extends IsolatedTestCase
{
    public function test_app_boots_with_sqlite_memory_default(): void
    {
        self::assertSame('testing', config('database.default'));
        self::assertSame(':memory:', config('database.connections.testing.database'));
        self::assertSame('sqlite', config('database.connections.testing.driver'));
    }

    public function test_app_key_is_set(): void
    {
        self::assertNotNull(config('app.key'));
        self::assertNotSame('', config('app.key'));
    }

    public function test_cache_session_queue_drivers_are_synchronous(): void
    {
        self::assertSame('array', config('cache.default'));
        self::assertSame('array', config('session.driver'));
        self::assertSame('sync', config('queue.default'));
    }

    public function test_assert_table_helpers(): void
    {
        Schema::create('foos', function ($table): void {
            $table->id();
            $table->string('name');
        });

        $this->assertTableExists('foos');
        $this->assertColumnExists('foos', 'name');
        $this->assertTableMissing('bars');
    }

    public function test_create_temp_path_yields_writable_directory(): void
    {
        $path = $this->createTempPath('my-suite');

        self::assertTrue(is_dir($path));
        self::assertTrue(is_writable($path));
        self::assertStringContainsString('laranail-test-', $path);
        self::assertStringContainsString('my-suite', $path);

        // Will be cleaned up at tearDown — write a sentinel file to verify.
        file_put_contents($path . '/sentinel.txt', 'written');
        self::assertFileExists($path . '/sentinel.txt');
    }
}
