<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Package\Tools\Providers\PackageToolsServiceProvider;
use Simtabi\Laranail\Package\Tools\Services\Asset\PublishTagRegistry;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

final class PackagePublishCommandTest extends TestCase
{
    private string $sandbox;

    /** @var array<string, mixed> */
    private array $publishGroupsBackup = [];

    protected function setUp(): void
    {
        parent::setUp();

        // $publishGroups is a process-wide static. Without a snapshot, groups
        // leak between tests and the assertions here start depending on
        // execution order.
        $this->publishGroupsBackup = ServiceProvider::$publishGroups;

        $this->sandbox = sys_get_temp_dir() . '/laranail-publish-' . bin2hex(random_bytes(6));

        File::ensureDirectoryExists($this->sandbox . '/source');
        File::ensureDirectoryExists($this->sandbox . '/public/vendor/blog');
        File::ensureDirectoryExists($this->sandbox . '/config');

        File::put($this->sandbox . '/source/app.css', 'body{}');
        File::put($this->sandbox . '/public/vendor/blog/stale.css', 'stale');
        File::put($this->sandbox . '/config/blog.php', '<?php return [];');

        $this->app->setBasePath($this->sandbox);
        config()->set('package-tools.assets.prune.roots', ['public/vendor']);
    }

    protected function tearDown(): void
    {
        ServiceProvider::$publishGroups = $this->publishGroupsBackup;
        File::deleteDirectory($this->sandbox);

        parent::tearDown();
    }

    /** @return array<int, class-string> */
    #[Override]
    protected function getPackageProviders($app): array
    {
        return [PackageToolsServiceProvider::class];
    }

    private function register(): PublishTagRegistry
    {
        $registry = $this->app->make(PublishTagRegistry::class);

        $registry->record(
            'blog-assets',
            'blog',
            [$this->sandbox . '/source' => $this->sandbox . '/public/vendor/blog'],
            cleanable: true,
        );

        $registry->record(
            'blog-config',
            'blog',
            [$this->sandbox . '/source/app.css' => $this->sandbox . '/config/blog.php'],
        );

        ServiceProvider::$publishGroups['blog-assets'] = [
            $this->sandbox . '/source' => $this->sandbox . '/public/vendor/blog',
        ];
        ServiceProvider::$publishGroups['blog-config'] = [
            $this->sandbox . '/source/app.css' => $this->sandbox . '/config/blog.php',
        ];

        return $registry;
    }

    // -----------------------------------------------------------------
    // Listing
    // -----------------------------------------------------------------

    #[Test]
    public function it_lists_registered_tags(): void
    {
        $this->register();

        $this->artisan('laranail::package-tools.publish', ['--list' => true])
            ->expectsOutputToContain('blog-assets')
            ->assertExitCode(0);
    }

    #[Test]
    public function list_json_is_parseable(): void
    {
        $this->register();

        $this->artisan('laranail::package-tools.publish', ['--list' => true, '--json' => true])
            ->assertExitCode(0);
    }

    // -----------------------------------------------------------------
    // Selection
    // -----------------------------------------------------------------

    #[Test]
    public function an_unknown_tag_fails(): void
    {
        $this->register();

        $this->artisan('laranail::package-tools.publish', ['--tag' => ['nope']])
            ->assertExitCode(1);
    }

    #[Test]
    public function no_selection_fails_rather_than_publishing_everything(): void
    {
        $this->register();

        $this->artisan('laranail::package-tools.publish')->assertExitCode(1);
    }

    // -----------------------------------------------------------------
    // --dry-run
    // -----------------------------------------------------------------

    #[Test]
    public function a_dry_run_changes_nothing(): void
    {
        $this->register();

        $this->artisan('laranail::package-tools.publish', [
            '--tag' => ['blog-assets'],
            '--clean' => true,
            '--dry-run' => true,
        ])->assertExitCode(0);

        self::assertFileExists(
            $this->sandbox . '/public/vendor/blog/stale.css',
            'A dry run deleted something.',
        );
    }

    // -----------------------------------------------------------------
    // --force is not --clean
    // -----------------------------------------------------------------

    #[Test]
    public function force_alone_never_deletes(): void
    {
        // The conflation this command exists to undo: in the code it replaces,
        // --force meant "recursively delete the destinations first".
        $this->register();

        $this->artisan('laranail::package-tools.publish', [
            '--tag' => ['blog-assets'],
            '--force' => true,
        ])->assertExitCode(0);

        self::assertFileExists(
            $this->sandbox . '/public/vendor/blog/stale.css',
            '--force deleted a destination. It means overwrite, not delete.',
        );
    }

    #[Test]
    public function clean_with_force_deletes_inside_a_prune_root(): void
    {
        $this->register();

        $this->artisan('laranail::package-tools.publish', [
            '--tag' => ['blog-assets'],
            '--clean' => true,
            '--force' => true,
        ])->assertExitCode(0);

        self::assertFileDoesNotExist($this->sandbox . '/public/vendor/blog/stale.css');
    }

    #[Test]
    public function clean_skips_a_destination_outside_every_prune_root(): void
    {
        // A package publishes to config/ and database/migrations/ too. Silently
        // deleting a published config file would be a far worse surprise than
        // saying it was left alone.
        $this->register();

        $this->artisan('laranail::package-tools.publish', [
            '--tag' => ['blog-config'],
            '--clean' => true,
            '--force' => true,
        ])->assertExitCode(0);

        self::assertFileExists(
            $this->sandbox . '/config/blog.php',
            'A clean deleted a published config file outside the prune root.',
        );
    }

    #[Test]
    public function clean_without_force_asks_first_and_declining_deletes_nothing(): void
    {
        $this->register();

        $this->artisan('laranail::package-tools.publish', [
            '--tag' => ['blog-assets'],
            '--clean' => true,
        ])
            ->expectsConfirmation('About to DELETE the destinations of: blog-assets. Continue?', 'no')
            ->assertExitCode(1);

        self::assertFileExists($this->sandbox . '/public/vendor/blog/stale.css');
    }

    #[Test]
    public function clean_without_force_proceeds_when_confirmed(): void
    {
        $this->register();

        $this->artisan('laranail::package-tools.publish', [
            '--tag' => ['blog-assets'],
            '--clean' => true,
        ])
            ->expectsConfirmation('About to DELETE the destinations of: blog-assets. Continue?', 'yes')
            ->assertExitCode(0);

        self::assertFileDoesNotExist($this->sandbox . '/public/vendor/blog/stale.css');
    }

    // -----------------------------------------------------------------
    // Misconfiguration
    // -----------------------------------------------------------------

    #[Test]
    public function a_misconfigured_prune_root_deletes_nothing(): void
    {
        // Finding 3. A guard with no usable roots refuses everything, which is
        // the right outcome: a clean that deletes nothing beats one that
        // deletes the document root.
        $this->register();
        config()->set('package-tools.assets.prune.roots', ['public']);

        $this->artisan('laranail::package-tools.publish', [
            '--tag' => ['blog-assets'],
            '--clean' => true,
            '--force' => true,
        ])->assertExitCode(0);

        self::assertFileExists($this->sandbox . '/public/vendor/blog/stale.css');
        self::assertDirectoryExists($this->sandbox . '/public');
    }
}
