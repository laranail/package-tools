<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Integration;

use Illuminate\Support\Facades\File;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\Package\Tools\Providers\PackageToolsServiceProvider;
use Simtabi\Laranail\Package\Tools\Services\Asset\PublishTagRegistry;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

/**
 * Booting a package must never delete anything.
 *
 * `bootPackageCustomPublishes()` used to delete the destination of every tag
 * marked `cleanBeforePublish`, gated only on `runningInConsole()`. Every
 * console command boots every provider, so `php artisan route:list` deleted the
 * published assets of any package that had asked for a clean — and they stayed
 * gone until someone re-published. The clean intent is recorded now and honoured
 * when publishing actually runs.
 */
final class BootDoesNotDeletePublishedAssetsTest extends TestCase
{
    private string $sandbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandbox = sys_get_temp_dir() . '/laranail-publish-boot-' . bin2hex(random_bytes(6));

        File::ensureDirectoryExists($this->sandbox . '/source');
        File::ensureDirectoryExists($this->sandbox . '/destination');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sandbox);

        parent::tearDown();
    }

    /**
     * @return array<int, class-string>
     */
    #[Override]
    protected function getPackageProviders($app): array
    {
        return [PackageToolsServiceProvider::class];
    }

    #[Test]
    public function booting_leaves_a_clean_marked_destination_untouched(): void
    {
        File::put($this->sandbox . '/destination/already-published.css', 'body{}');

        $this->bootPackageWithCleanablePublish();

        $this->assertFileExists(
            $this->sandbox . '/destination/already-published.css',
            'Boot deleted a published file. Boot must never delete.',
        );
    }

    #[Test]
    public function booting_leaves_a_clean_marked_destination_directory_untouched(): void
    {
        File::ensureDirectoryExists($this->sandbox . '/destination/nested');
        File::put($this->sandbox . '/destination/nested/app.js', '/* built */');

        $this->bootPackageWithCleanablePublish();

        $this->assertDirectoryExists($this->sandbox . '/destination/nested');
        $this->assertFileExists($this->sandbox . '/destination/nested/app.js');
    }

    #[Test]
    public function the_clean_request_is_recorded_rather_than_acted_on(): void
    {
        $this->bootPackageWithCleanablePublish();

        $entry = $this->app->make(PublishTagRegistry::class)->get('sandbox-assets');

        $this->assertNotNull($entry, 'The publish tag was not recorded.');
        $this->assertTrue($entry->cleanable, 'The cleanBeforePublish request was lost.');
        $this->assertSame('sandbox-package', $entry->package);
        $this->assertContains($this->sandbox . '/destination', $entry->destinations());
    }

    #[Test]
    public function a_tag_without_a_clean_request_is_recorded_as_not_cleanable(): void
    {
        $this->bootPackageWithCleanablePublish(clean: false);

        $this->assertFalse($this->app->make(PublishTagRegistry::class)->isCleanable('sandbox-assets'));
    }

    #[Test]
    public function the_registry_is_bound_as_a_singleton(): void
    {
        $this->assertTrue($this->app->bound(PublishTagRegistry::class));
        $this->assertSame(
            $this->app->make(PublishTagRegistry::class),
            $this->app->make(PublishTagRegistry::class),
        );
    }

    private function bootPackageWithCleanablePublish(bool $clean = true): void
    {
        $source = $this->sandbox . '/source';
        $destination = $this->sandbox . '/destination';

        $provider = new class($this->app, $source, $destination, $clean) extends PackageServiceProvider
        {
            public function __construct(
                $app,
                private readonly string $source,
                private readonly string $destination,
                private readonly bool $clean,
            ) {
                parent::__construct($app);
            }

            public function configurePackage(Package $package): void
            {
                $package
                    ->setName('sandbox-vendor/sandbox-package')
                    ->publish([$this->source => $this->destination], 'sandbox-assets', $this->clean);
            }
        };

        $provider->register();
        $provider->boot();
    }
}
