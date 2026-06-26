<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\Package\Tools\Providers\PackageToolsServiceProvider;

final class PackageAuditCommandTest extends TestCase
{
    private string $sandbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandbox = sys_get_temp_dir() . '/audit-cmd-' . uniqid();
        File::ensureDirectoryExists($this->sandbox);
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
        return [PackageToolsServiceProvider::class];
    }

    public function test_returns_zero_when_no_advisories(): void
    {
        Http::fake([
            'api.osv.dev/*' => Http::response([
                'results' => array_fill(0, 3, ['vulns' => []]),
            ]),
        ]);

        $exit = Artisan::call('laranail::package-tools.audit');
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('No known vulnerabilities found', $output);
    }

    public function test_returns_failure_when_advisories_present(): void
    {
        Http::fake([
            'api.osv.dev/*' => Http::response([
                'results' => [
                    ['vulns' => [[
                        'id' => 'GHSA-test-1',
                        'summary' => 'Test advisory',
                    ]]],
                    ['vulns' => []],
                    ['vulns' => []],
                ],
            ]),
        ]);

        $exit = Artisan::call('laranail::package-tools.audit');
        $output = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Found vulnerabilities', $output);
        $this->assertStringContainsString('GHSA-test-1', $output);
    }

    public function test_json_flag_emits_machine_readable_output(): void
    {
        Http::fake([
            'api.osv.dev/*' => Http::response([
                'results' => array_fill(0, 3, ['vulns' => []]),
            ]),
        ]);

        $exit = Artisan::call('laranail::package-tools.audit', ['--json' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertSame(3, $decoded['scanned']);
        $this->assertSame(0, $decoded['vulnerable_count']);
    }

    public function test_no_dev_flag_skips_dev_packages(): void
    {
        Http::fake([
            'api.osv.dev/*' => Http::response([
                'results' => array_fill(0, 2, ['vulns' => []]),
            ]),
        ]);

        $exit = Artisan::call('laranail::package-tools.audit', ['--no-dev' => true, '--json' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $decoded = json_decode($output, true);
        $this->assertSame(2, $decoded['scanned']);
    }
}
