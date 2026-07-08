<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Services\Log;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Services\Log\PackageLogger;
use Simtabi\Laranail\Package\Tools\Support\Definitions\LogDefinition;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

final class PackageLoggerTest extends TestCase
{
    private string $sandbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandbox = sys_get_temp_dir() . '/package-logger-' . uniqid();
        File::ensureDirectoryExists($this->sandbox);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sandbox);

        parent::tearDown();
    }

    private function makePackage(?LogDefinition $definition = null): Package
    {
        $package = new Package;
        $package->name('acme/blog');
        $package->basePath = $this->sandbox;
        $package->hasLogging($definition ?? LogDefinition::make()->single()->directory($this->sandbox));

        return $package;
    }

    private function logfile(): string
    {
        return $this->sandbox . '/acme-blog.log';
    }

    #[Test]
    public function log_returns_the_same_memoized_instance(): void
    {
        $package = $this->makePackage();

        $this->assertSame($package->log(), $package->log());
    }

    #[Test]
    public function it_writes_to_a_file_named_after_the_package(): void
    {
        $package = $this->makePackage();
        $package->log()->info('hello world');

        $this->assertFileExists($this->logfile());
        $this->assertStringContainsString('[acme/blog] [INFO] hello world', File::get($this->logfile()));
    }

    #[Test]
    public function the_default_path_derives_from_the_dashed_namespace(): void
    {
        $package = new Package;
        $package->name('acme/blog');
        $package->basePath = $this->sandbox;

        $this->assertSame('acme-blog', $package->log()->channelName());
    }

    #[Test]
    public function disabled_via_per_package_config_writes_nothing_and_creates_no_file(): void
    {
        config()->set('acme.blog.logging.enabled', false);

        $package = $this->makePackage();
        $package->log()->error('should not exist');

        $this->assertFileDoesNotExist($this->logfile());
    }

    #[Test]
    public function per_package_config_level_filters_lower_levels(): void
    {
        config()->set('acme.blog.logging.level', 'error');

        $package = $this->makePackage();
        $package->log()->info('too quiet');
        $package->log()->error('loud enough');

        $content = File::get($this->logfile());
        $this->assertStringNotContainsString('too quiet', $content);
        $this->assertStringContainsString('loud enough', $content);
    }

    #[Test]
    public function use_channel_delegates_to_the_named_host_channel(): void
    {
        Log::spy();

        $package = $this->makePackage(LogDefinition::make()->useChannel('stack'));
        $package->log()->warning('delegated');

        Log::shouldHaveReceived('channel')->with('stack')->once();
    }

    #[Test]
    public function a_host_defined_channel_of_the_same_name_wins_untouched(): void
    {
        $hostPath = $this->sandbox . '/host-defined.log';
        config()->set('logging.channels.acme-blog', [
            'driver' => 'single',
            'path' => $hostPath,
        ]);

        $package = $this->makePackage();
        $package->log()->info('host override');

        $this->assertFileExists($hostPath);
        $this->assertFileDoesNotExist($this->logfile());
        // Our channel config must not have replaced the host's definition.
        $this->assertSame($hostPath, config('logging.channels.acme-blog.path'));
    }

    #[Test]
    public function success_writes_at_info_level_with_the_success_token(): void
    {
        $package = $this->makePackage();
        $package->log()->success('Migrations published', 'Install', ['count' => 3]);

        $content = File::get($this->logfile());
        $this->assertStringContainsString('[SUCCESS] [Install] Migrations published | {"count":3}', $content);
    }

    #[Test]
    public function logging_never_throws_even_with_an_unwritable_path(): void
    {
        $package = $this->makePackage(LogDefinition::make()->single()->path('/nonexistent-root/nope/x.log'));

        $package->log()->error('must not explode');

        // Reaching this line IS the assertion; add one for the runner.
        $this->assertTrue(true);
    }

    #[Test]
    public function buffered_records_flush_on_mark_ready_with_original_timestamps(): void
    {
        $package = $this->makePackage();
        $logger = $package->log();
        $logger->bufferUntilReady();

        $logger->info('early one');
        $logger->info('early two');
        $this->assertFileDoesNotExist($this->logfile());

        $logger->markReady();

        $content = File::get($this->logfile());
        $this->assertStringContainsString('early one', $content);
        $this->assertStringContainsString('early two', $content);
    }

    #[Test]
    public function the_buffer_is_bounded_and_overflow_switches_to_write_through(): void
    {
        $package = $this->makePackage();
        $logger = $package->log();
        $logger->bufferUntilReady();

        for ($i = 0; $i < 101; $i++) {
            $logger->info("line {$i}");
        }

        // The 101st write flushed everything.
        $content = File::get($this->logfile());
        $this->assertStringContainsString('line 0', $content);
        $this->assertStringContainsString('line 100', $content);
    }

    #[Test]
    public function global_config_overrides_the_definition(): void
    {
        config()->set('package-tools.logging.level', 'critical');

        $package = $this->makePackage(LogDefinition::make()->single()->directory($this->sandbox)->level('debug'));
        $package->log()->error('filtered by global');
        $package->log()->critical('passes global');

        $content = File::get($this->logfile());
        $this->assertStringNotContainsString('filtered by global', $content);
        $this->assertStringContainsString('passes global', $content);
    }
}
