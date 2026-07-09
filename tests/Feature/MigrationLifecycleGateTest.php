<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature;

use Illuminate\Database\Migrations\Migrator;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\Package\Tools\Providers\PackageToolsServiceProvider;
use Simtabi\Laranail\Package\Tools\Services\Database\FailureAwareMigrator;

/**
 * With migration failure detection disabled, the migrator is left as the
 * plain framework class — no decoration.
 */
final class MigrationLifecycleGateTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [PackageToolsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('package-tools.migrations.failure_detection.enabled', false);
    }

    public function test_the_gate_off_leaves_the_migrator_undecorated(): void
    {
        $migrator = $this->app->make('migrator');

        $this->assertInstanceOf(Migrator::class, $migrator);
        $this->assertNotInstanceOf(FailureAwareMigrator::class, $migrator);
    }
}
