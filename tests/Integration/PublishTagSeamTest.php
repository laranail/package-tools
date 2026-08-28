<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Integration;

use Closure;
use Override;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\Package\Tools\Services\Asset\PublishTagRegistry;
use Simtabi\Laranail\Package\Tools\Providers\PackageToolsServiceProvider;

/**
 * Recording happens in ONE place — the `publishes()` override — so every
 * `Process*` trait is captured without each one having to remember.
 *
 * These assert the seam actually catches the traits, which is the claim that
 * would otherwise silently stop being true the next time a trait is added.
 */
final class PublishTagSeamTest extends TestCase
{
    private string $sandbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandbox = sys_get_temp_dir() . '/laranail-seam-' . bin2hex(random_bytes(6));

        foreach (['config', 'resources/views', 'resources/lang', 'database/migrations', 'resources/dist'] as $dir) {
            File::ensureDirectoryExists($this->sandbox . '/' . $dir);
        }

        File::put($this->sandbox . '/config/seam.php', '<?php return [];');
        File::put($this->sandbox . '/resources/views/x.blade.php', 'x');
        File::put($this->sandbox . '/database/migrations/0001_01_01_000000_create_x.php', '<?php');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sandbox);

        parent::tearDown();
    }

    #[Test]
    public function config_views_and_migrations_are_all_recorded_without_touching_their_traits(): void
    {
        $sandbox = $this->sandbox;

        $this->bootPackage(function (Package $package) use ($sandbox): void {
            $package
                ->setName('seam-vendor/seam')
                ->setPathFrom($sandbox)
                ->hasConfigFile('seam')
                ->hasViews()
                ->hasMigrations();
        });

        $tags = $this->registry()->tags();

        self::assertNotEmpty($tags, 'The publishes() seam recorded nothing.');

        foreach ($this->registry()->all() as $entry) {
            self::assertSame('seam', $entry->package);
            self::assertNotEmpty($entry->paths);
        }
    }

    #[Test]
    public function a_clean_request_survives_the_seam(): void
    {
        $sandbox = $this->sandbox;

        $this->bootPackage(function (Package $package) use ($sandbox): void {
            $package
                ->setName('seam-vendor/seam')
                ->setPathFrom($sandbox)
                ->publish([$sandbox . '/resources/dist' => $sandbox . '/out'], 'seam-assets', true);
        });

        self::assertTrue($this->registry()->isCleanable('seam-assets'));
    }

    #[Test]
    public function a_tag_is_recorded_once_even_when_two_call_sites_publish_it(): void
    {
        $sandbox = $this->sandbox;

        $this->bootPackage(function (Package $package) use ($sandbox): void {
            $package
                ->setName('seam-vendor/seam')
                ->setPathFrom($sandbox)
                ->publish([$sandbox . '/config' => $sandbox . '/out-a'], 'shared-tag')
                ->publish([$sandbox . '/resources/dist' => $sandbox . '/out-b'], 'shared-tag', true);
        });

        $entry = $this->registry()->get('shared-tag');

        self::assertNotNull($entry);
        self::assertCount(2, $entry->paths);
        self::assertTrue($entry->cleanable, 'cleanable must be sticky across repeat records.');
    }

    /** @return array<int, class-string> */
    #[Override]
    protected function getPackageProviders($app): array
    {
        return [PackageToolsServiceProvider::class];
    }

    private function registry(): PublishTagRegistry
    {
        return $this->app->make(PublishTagRegistry::class);
    }

    private function bootPackage(callable $configure): void
    {
        $provider = new class($this->app, $configure) extends PackageServiceProvider
        {
            public function __construct($app, private readonly Closure $configure)
            {
                parent::__construct($app);
            }

            public function configurePackage(Package $package): void
            {
                ($this->configure)($package);
            }
        };

        $provider->register();
        $provider->boot();
    }
}
