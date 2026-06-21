<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\Package\Tools\Providers\PackageToolsServiceProvider;

final class PackageIdeHelperCommandTest extends TestCase
{
    private string $sandbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandbox = sys_get_temp_dir() . '/ide-cmd-' . uniqid();
        File::ensureDirectoryExists($this->sandbox);

        // Mirror the facade fixture into the sandbox under a fresh PSR-4 root.
        File::copyDirectory(__DIR__ . '/../../fixtures/facade', $this->sandbox . '/Contracts');

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

    public function test_generates_facades_for_annotated_contracts(): void
    {
        $exit = Artisan::call('laranail::package-tools.ide-helper', [
            '--source' => 'Contracts',
            '--source-namespace' => 'Simtabi\\Laranail\\Package\\Tools\\Tests\\Fixtures\\Facade',
            '--output' => 'Facades',
            '--facade-namespace' => 'App\\Facades',
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Generated', $output);
        $this->assertFileExists($this->sandbox . '/Facades/Greeter.php');
        $this->assertFileExists($this->sandbox . '/Facades/Counter.php');
    }

    public function test_warns_when_no_contracts_found(): void
    {
        File::ensureDirectoryExists($this->sandbox . '/Empty');

        $exit = Artisan::call('laranail::package-tools.ide-helper', [
            '--source' => 'Empty',
            '--source-namespace' => 'App',
            '--output' => 'Facades',
            '--facade-namespace' => 'App\\Facades',
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('No #[AsFacade]', $output);
    }
}
