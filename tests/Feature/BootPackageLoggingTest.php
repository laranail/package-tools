<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Orchestra\Testbench\TestCase;
use Override;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\Package\Tools\Providers\PackageToolsServiceProvider;
use Simtabi\Laranail\Package\Tools\Services\Log\PackageLogger;
use Simtabi\Laranail\Package\Tools\Support\Definitions\LogDefinition;

/**
 * $package->log() end to end: lines written INSIDE configurePackage()
 * (before the package's config merges) land in the per-package logfile
 * with the exact bracketed format, host config disables/overrides work,
 * and the logger is reachable through its container alias at runtime.
 */
final class BootPackageLoggingTest extends TestCase
{
    private static string $sandbox = '';

    #[Override]
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$sandbox = sys_get_temp_dir() . '/boot-logging-' . uniqid();
    }

    protected function setUp(): void
    {
        LogDemoProvider::$sandbox = self::$sandbox;
        if (! is_dir(self::$sandbox)) {
            mkdir(self::$sandbox, 0777, true);
        }

        parent::setUp();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(self::$sandbox);

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            PackageToolsServiceProvider::class,
            LogDemoProvider::class,
        ];
    }

    protected function disableViaPerPackageConfig(Application $app): void
    {
        $app['config']->set('acme.logdemo.logging.enabled', false);
    }

    private function logfile(): string
    {
        return self::$sandbox . '/acme-logdemo.log';
    }

    public function test_early_logging_inside_configure_package_reaches_the_logfile(): void
    {
        $this->assertFileExists($this->logfile());

        $content = File::get($this->logfile());
        $this->assertMatchesRegularExpression(
            '/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3}\] \[acme\/logdemo\] \[INFO\] \[Register\] booted$/m',
            $content,
        );
    }

    public function test_success_line_lands_with_token_and_context(): void
    {
        $content = File::get($this->logfile());

        $this->assertStringContainsString(
            '[acme/logdemo] [SUCCESS] [Install] ready | {"step":1}',
            $content,
        );
    }

    public function test_the_named_channel_is_visible_to_the_host(): void
    {
        $this->assertIsArray(config('logging.channels.acme-logdemo'));
        $this->assertSame('single', config('logging.channels.acme-logdemo.driver'));
    }

    #[DefineEnvironment('disableViaPerPackageConfig')]
    public function test_per_package_config_disable_suppresses_the_file(): void
    {
        $this->assertFileDoesNotExist($this->logfile());
    }

    public function test_container_alias_resolves_the_same_logger(): void
    {
        $viaAlias = $this->app->make('laranail.logger.acme-logdemo');
        $viaPackage = $this->app->getProvider(LogDemoProvider::class)->package->log();

        $this->assertInstanceOf(PackageLogger::class, $viaAlias);
        $this->assertSame($viaPackage, $viaAlias);
    }

    public function test_runtime_logging_after_boot_appends(): void
    {
        $this->app->make('laranail.logger.acme-logdemo')->warning('later on', 'Runtime');

        $this->assertStringContainsString('[WARNING] [Runtime] later on', File::get($this->logfile()));
    }
}

final class LogDemoProvider extends PackageServiceProvider
{
    public static string $sandbox = '';

    public function configurePackage(Package $package): void
    {
        $package->setName('acme/logdemo');
        $package->basePath = sys_get_temp_dir();

        $package->hasLogging(LogDefinition::make()->single()->directory(self::$sandbox));

        // The early-logging case: these run during register(), before the
        // package's config has merged.
        $package->log()->info('booted', 'Register');
        $package->log()->success('ready', 'Install', ['step' => 1]);
    }
}
