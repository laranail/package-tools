<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Queue;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\Package\Tools\Enums\SeederExecutionMode;
use Simtabi\Laranail\Package\Tools\Jobs\RunSeederBundleJob;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\Package\Tools\Providers\PackageToolsServiceProvider;
use Simtabi\Laranail\Package\Tools\Support\Definitions\AutoSeederDefinition;

final class PackageSeedCommandTest extends TestCase
{
    protected function setUp(): void
    {
        SeedCommandLedger::reset();

        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            PackageToolsServiceProvider::class,
            SeedCommandInlineProvider::class,
            SeedCommandBackgroundProvider::class,
        ];
    }

    public function test_key_filter_runs_only_that_bundle_inline(): void
    {
        $this->artisan('laranail::package-tools.seed', ['--key' => ['t/inline']])
            ->assertExitCode(0);

        $this->assertSame(['inline'], SeedCommandLedger::$ran);
    }

    public function test_a_background_bundle_dispatches_the_job_without_any_flag(): void
    {
        Queue::fake();

        $this->artisan('laranail::package-tools.seed', ['--key' => ['t/background']])
            ->assertExitCode(0);

        Queue::assertPushed(RunSeederBundleJob::class,
            // The payload carries ONLY the key + mode enum — never the
            // definition (closures don't serialize).
            fn (RunSeederBundleJob $job): bool => $job->bundleKey === 't/background'
            && $job->mode === SeederExecutionMode::Queued);
        $this->assertSame([], SeedCommandLedger::$ran);
    }

    public function test_sync_flag_overrides_a_background_bundle(): void
    {
        Queue::fake();

        $this->artisan('laranail::package-tools.seed', ['--key' => ['t/background'], '--sync' => true])
            ->assertExitCode(0);

        Queue::assertNothingPushed();
        $this->assertSame(['background'], SeedCommandLedger::$ran);
    }

    public function test_queued_flag_overrides_an_inline_bundle(): void
    {
        Queue::fake();

        $this->artisan('laranail::package-tools.seed', ['--key' => ['t/inline'], '--queued' => true])
            ->assertExitCode(0);

        Queue::assertPushed(RunSeederBundleJob::class);
        $this->assertSame([], SeedCommandLedger::$ran);
    }

    public function test_production_requires_force(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('laranail::package-tools.seed', ['--key' => ['t/inline']])
            ->assertExitCode(1);

        $this->assertSame([], SeedCommandLedger::$ran);

        $this->artisan('laranail::package-tools.seed', ['--key' => ['t/inline'], '--force' => true])
            ->assertExitCode(0);

        $this->assertSame(['inline'], SeedCommandLedger::$ran);
    }

    public function test_status_renders_the_tracker_table_without_executing(): void
    {
        $this->artisan('laranail::package-tools.seed', ['--status' => true])
            ->expectsOutputToContain('t/inline')
            ->assertExitCode(0);

        $this->assertSame([], SeedCommandLedger::$ran);
    }
}

final class SeedCommandLedger
{
    /** @var list<string> */
    public static array $ran = [];

    public static function reset(): void
    {
        self::$ran = [];
    }
}

final class SeedCommandInlineSeeder extends Seeder
{
    public function run(): void
    {
        SeedCommandLedger::$ran[] = 'inline';
    }
}

final class SeedCommandBackgroundSeeder extends Seeder
{
    public function run(): void
    {
        SeedCommandLedger::$ran[] = 'background';
    }
}

final class SeedCommandInlineProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->setName('test/seed-command-inline');
        $package->basePath = sys_get_temp_dir();

        $package->hasPackageSeeders(
            AutoSeederDefinition::make('t/inline')->seeders([SeedCommandInlineSeeder::class]),
        );
    }
}

final class SeedCommandBackgroundProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->setName('test/seed-command-background');
        $package->basePath = sys_get_temp_dir();

        $package->hasPackageSeeders(
            AutoSeederDefinition::make('t/background')
                ->seeders([SeedCommandBackgroundSeeder::class])
                ->runsInBackground(),
        );
    }
}
