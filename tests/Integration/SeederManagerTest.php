<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Integration;

use Illuminate\Database\Seeder;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\Package\Tools\Providers\PackageToolsServiceProvider;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederManager;
use Simtabi\Laranail\Package\Tools\ValueObjects\SeederExecutionStats;

final class SeederManagerTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [PackageToolsServiceProvider::class];
    }

    public function test_auto_seed_registers_into_shared_registry(): void
    {
        ManagerStubCounter::reset();
        $manager = $this->app->make(SeederManager::class);

        $manager->autoSeed('Acme\\Blog', [ManagerStubSeeder::class], 'Acme\\Blog');

        $this->assertNotNull($manager->registry()->get('Acme\\Blog'));
        $this->assertSame(
            [ManagerStubSeeder::class],
            $manager->registry()->get('Acme\\Blog')->seeders(),
        );
    }

    public function test_builder_executes_explicit_classes_and_returns_stats(): void
    {
        ManagerStubCounter::reset();
        $manager = $this->app->make(SeederManager::class);

        $stats = $manager->seeders()->classes([ManagerStubSeeder::class])->execute();

        $this->assertInstanceOf(SeederExecutionStats::class, $stats);
        $this->assertSame(1, $stats->success);
        $this->assertSame(0, $stats->failed);
        $this->assertSame(['ran'], ManagerStubCounter::$ran);
    }

    public function test_builder_only_filter_excludes_others(): void
    {
        $manager = $this->app->make(SeederManager::class);

        $resolved = $manager->seeders()
            ->classes([ManagerStubSeeder::class, ManagerOtherSeeder::class])
            ->only(['ManagerOtherSeeder'])
            ->discover();

        $this->assertSame([ManagerOtherSeeder::class], $resolved);
    }
}

final class ManagerStubCounter
{
    /** @var list<string> */
    public static array $ran = [];

    public static function reset(): void
    {
        self::$ran = [];
    }
}

final class ManagerStubSeeder extends Seeder
{
    public function run(): void
    {
        ManagerStubCounter::$ran[] = 'ran';
    }
}

final class ManagerOtherSeeder extends Seeder {}
