<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature\Commands;

use Override;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\ServiceProvider;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;
use Simtabi\Laranail\Package\Tools\Services\Asset\PublishTagRegistry;
use Simtabi\Laranail\Package\Tools\Providers\PackageToolsServiceProvider;

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
        config()->set('laranail.package-tools.assets.prune.roots', ['public/vendor']);
    }

    protected function tearDown(): void
    {
        ServiceProvider::$publishGroups = $this->publishGroupsBackup;
        File::deleteDirectory($this->sandbox);

        parent::tearDown();
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
            '--tag'     => ['blog-assets'],
            '--clean'   => true,
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
            '--tag'   => ['blog-assets'],
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
            '--tag'   => ['blog-assets'],
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
            '--tag'   => ['blog-config'],
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
            '--tag'   => ['blog-assets'],
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
            '--tag'   => ['blog-assets'],
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
        config()->set('laranail.package-tools.assets.prune.roots', ['public']);

        $this->artisan('laranail::package-tools.publish', [
            '--tag'   => ['blog-assets'],
            '--clean' => true,
            '--force' => true,
        ])->assertExitCode(0);

        self::assertFileExists($this->sandbox . '/public/vendor/blog/stale.css');
        self::assertDirectoryExists($this->sandbox . '/public');
    }

    #[Test]
    public function external_publishes_tags_this_package_did_not_register(): void
    {
        // The command this generalises hardcoded Livewire's and Horizon's
        // provider FQCNs, so it published exactly the two packages someone
        // thought of. publishableGroups() is what vendor:publish itself reads.
        $this->register();
        $this->registerExternal('livewire:assets');

        $this->artisan('laranail::package-tools.publish', ['--external' => true, '--force' => true])
            ->assertExitCode(0);

        self::assertFileExists($this->sandbox . '/public/vendor/livewire:assets/vendor.js');
    }

    #[Test]
    public function external_leaves_laranail_tags_alone(): void
    {
        $this->register();
        $this->registerExternal('horizon-assets');

        $this->artisan('laranail::package-tools.publish', ['--external' => true, '--force' => true, '--dry-run' => true])
            ->doesntExpectOutputToContain('blog-assets')
            ->assertExitCode(0);
    }

    #[Test]
    public function external_with_all_publishes_both(): void
    {
        $this->register();
        $this->registerExternal('horizon-assets');

        $this->artisan('laranail::package-tools.publish', ['--all' => true, '--external' => true, '--dry-run' => true])
            ->expectsOutputToContain('blog-assets')
            ->expectsOutputToContain('horizon-assets')
            ->assertExitCode(0);
    }

    #[Test]
    public function external_says_so_when_there_is_nothing_external(): void
    {
        // A distinct message from the generic "nothing to publish": the caller
        // asked a specific question and the answer is "none", not "you gave me
        // no arguments".
        //
        // $publishGroups is cleared first because a real application always has
        // external tags — Laravel's own providers register several, which is
        // what made an earlier version of this test fail. That failure was the
        // code being right.
        $this->register();
        ServiceProvider::$publishGroups = array_intersect_key(
            ServiceProvider::$publishGroups,
            ['blog-assets' => true, 'blog-config' => true],
        );

        $this->artisan('laranail::package-tools.publish', ['--external' => true])
            ->expectsOutputToContain('No external publish tags')
            ->assertExitCode(1);
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
    // --external
    // -----------------------------------------------------------------

    /**
     * A tag registered by something that is not a laranail package —
     * Livewire, Horizon, anything else the application publishes.
     *
     * Every *other* group is dropped first, and that is not tidiness. A real
     * application's external groups include the framework's own config
     * publishing, so `--external --force` under Testbench writes config files
     * into the skeleton — where they persist across runs and break unrelated
     * tests that assert a key is absent. It did exactly that before this
     * isolation was added.
     */
    private function registerExternal(string $tag): void
    {
        File::ensureDirectoryExists($this->sandbox . '/external-src');
        File::put($this->sandbox . '/external-src/vendor.js', 'console.log(1)');

        ServiceProvider::$publishGroups = array_intersect_key(
            ServiceProvider::$publishGroups,
            ['blog-assets' => true, 'blog-config' => true],
        );

        ServiceProvider::$publishGroups[$tag] = [
            $this->sandbox . '/external-src' => $this->sandbox . '/public/vendor/' . $tag,
        ];
    }
}
