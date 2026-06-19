<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Tests\Feature\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\PackageTools\Providers\LaranailToolsServiceProvider;

final class PackageSbomCommandTest extends TestCase
{
    private string $sandbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandbox = sys_get_temp_dir() . '/sbom-cmd-' . uniqid();
        File::ensureDirectoryExists($this->sandbox);
        File::copy(__DIR__ . '/../../fixtures/sbom/composer.json', $this->sandbox . '/composer.json');
        File::copy(__DIR__ . '/../../fixtures/sbom/composer.lock', $this->sandbox . '/composer.lock');

        $this->app->setBasePath($this->sandbox);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        File::deleteDirectory($this->sandbox);
    }

    protected function getPackageProviders($app): array
    {
        return [LaranailToolsServiceProvider::class];
    }

    public function test_print_emits_cyclonedx_json_to_stdout(): void
    {
        $exit = Artisan::call('laranail::package-tools.sbom', ['--print' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('"bomFormat": "CycloneDX"', $output);
        $this->assertStringContainsString('"specVersion": "1.5"', $output);
    }

    public function test_writes_file_to_default_output(): void
    {
        $exit = Artisan::call('laranail::package-tools.sbom');

        $this->assertSame(0, $exit);
        $this->assertFileExists($this->sandbox . '/sbom.json');

        $decoded = json_decode(File::get($this->sandbox . '/sbom.json'), true);
        $this->assertSame('CycloneDX', $decoded['bomFormat']);
    }

    public function test_returns_failure_when_composer_lock_missing(): void
    {
        File::delete($this->sandbox . '/composer.lock');

        $exit = Artisan::call('laranail::package-tools.sbom', ['--print' => true]);
        $output = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('SBOM generation failed', $output);
    }
}
