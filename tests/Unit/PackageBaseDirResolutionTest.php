<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit;

use Illuminate\Contracts\Foundation\Application;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Tests\Fixtures\SamplePackage\Providers\SampleServiceProvider;

final class PackageBaseDirResolutionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    #[Test]
    public function it_resolves_the_package_root_not_the_src_directory(): void
    {
        $app = Mockery::mock(Application::class);
        $provider = new SampleServiceProvider($app);

        $baseDir = $provider->resolvePackageBaseDir();

        $expectedRoot = dirname(__DIR__) . '/fixtures/sample-package';

        $this->assertSame(realpath($expectedRoot), realpath($baseDir));
        $this->assertStringEndsWith('sample-package', $baseDir);
        $this->assertFalse(
            str_ends_with($baseDir, DIRECTORY_SEPARATOR . 'src'),
            'Base dir must resolve to the package root, not the src/ directory.'
        );
    }

    #[Test]
    public function resource_paths_resolve_against_the_package_root(): void
    {
        $app = Mockery::mock(Application::class);
        $provider = new SampleServiceProvider($app);

        $root = $provider->resolvePackageBaseDir();
        $package = (new Package)->setPathFrom($root);

        // All resource directories are base-relative (no climbing out of root).
        $this->assertSame($root . '/resources/views', $package->basePath('/resources/views'));
        $this->assertSame($root . '/resources/lang', $package->basePath('/resources/lang'));
        $this->assertSame($root . '/resources/dist', $package->basePath('/resources/dist'));
        $this->assertSame($root . '/database/migrations', $package->basePath('database/migrations'));
    }
}
