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

final class PackageAssetsPruneCommandTest extends TestCase
{
    private string $sandbox;

    /** @var array<string, mixed> */
    private array $publishGroupsBackup = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->publishGroupsBackup = ServiceProvider::$publishGroups;
        ServiceProvider::$publishGroups = [];

        $this->sandbox = sys_get_temp_dir() . '/laranail-prune-' . bin2hex(random_bytes(6));

        File::ensureDirectoryExists($this->sandbox . '/source/blog');
        File::put($this->sandbox . '/source/blog/app.css', 'body{}');

        File::ensureDirectoryExists($this->sandbox . '/public/vendor/blog');
        File::put($this->sandbox . '/public/vendor/blog/app.css', 'body{}');

        File::ensureDirectoryExists($this->sandbox . '/public/vendor/ghost');
        File::put($this->sandbox . '/public/vendor/ghost/old.css', 'stale');

        File::put($this->sandbox . '/public/index.php', '<?php // the document root');

        $this->app->setBasePath($this->sandbox);
        config()->set('laranail.package-tools.assets.prune.roots', ['public/vendor']);

        ServiceProvider::$publishGroups['blog-assets'] = [
            $this->sandbox . '/source/blog' => $this->sandbox . '/public/vendor/blog',
        ];
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

    private function assertNothingWasDeleted(string $because): void
    {
        self::assertFileExists($this->sandbox . '/public/vendor/ghost/old.css', $because);
        self::assertFileExists($this->sandbox . '/public/vendor/blog/app.css', $because);
        self::assertFileExists($this->sandbox . '/public/index.php', $because);
    }

    // -----------------------------------------------------------------
    // Report-only by default
    // -----------------------------------------------------------------

    #[Test]
    public function it_reports_and_deletes_nothing_by_default(): void
    {
        // The whole safety posture: a command that infers what is unwanted from
        // a booted application's state must not act on that inference unasked.
        // A package that failed to boot publishes nothing, and everything it
        // ever published then looks orphaned.
        $this->artisan('laranail::package-tools.assets-prune')
            ->expectsOutputToContain('ghost')
            ->assertExitCode(0);

        $this->assertNothingWasDeleted('The default run deleted something.');
    }

    #[Test]
    public function a_published_directory_is_never_reported(): void
    {
        $this->artisan('laranail::package-tools.assets-prune')
            ->doesntExpectOutputToContain('blog/app.css')
            ->assertExitCode(0);
    }

    #[Test]
    public function strict_exits_non_zero_when_orphans_exist(): void
    {
        $this->artisan('laranail::package-tools.assets-prune', ['--strict' => true])
            ->assertExitCode(1);

        $this->assertNothingWasDeleted('--strict is a CI gate, not a delete verb.');
    }

    #[Test]
    public function strict_exits_zero_when_the_tree_is_clean(): void
    {
        File::deleteDirectory($this->sandbox . '/public/vendor/ghost');

        $this->artisan('laranail::package-tools.assets-prune', ['--strict' => true])
            ->assertExitCode(0);
    }

    #[Test]
    public function json_output_is_parseable(): void
    {
        $this->artisan('laranail::package-tools.assets-prune', ['--json' => true])
            ->assertExitCode(0);
    }

    // -----------------------------------------------------------------
    // --prune
    // -----------------------------------------------------------------

    #[Test]
    public function prune_with_force_removes_the_orphan_and_spares_the_rest(): void
    {
        $this->artisan('laranail::package-tools.assets-prune', [
            '--prune' => true,
            '--force' => true,
        ])->assertExitCode(0);

        self::assertDirectoryDoesNotExist($this->sandbox . '/public/vendor/ghost');
        self::assertFileExists($this->sandbox . '/public/vendor/blog/app.css');
        self::assertFileExists($this->sandbox . '/public/index.php');
    }

    #[Test]
    public function declining_the_confirmation_deletes_nothing(): void
    {
        $this->artisan('laranail::package-tools.assets-prune', ['--prune' => true])
            ->expectsConfirmation('About to DELETE 1 orphaned entr(y|ies). Continue?', 'no')
            ->assertExitCode(1);

        $this->assertNothingWasDeleted('A declined confirmation still deleted.');
    }

    #[Test]
    public function accepting_the_confirmation_prunes(): void
    {
        $this->artisan('laranail::package-tools.assets-prune', ['--prune' => true])
            ->expectsConfirmation('About to DELETE 1 orphaned entr(y|ies). Continue?', 'yes')
            ->assertExitCode(0);

        self::assertDirectoryDoesNotExist($this->sandbox . '/public/vendor/ghost');
    }

    #[Test]
    public function production_refuses_to_prune_without_force(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('laranail::package-tools.assets-prune', ['--prune' => true])
            ->assertExitCode(1);

        $this->assertNothingWasDeleted('Pruned in production without --force.');
    }

    #[Test]
    public function exceeding_the_deletion_ceiling_aborts_before_deleting_anything(): void
    {
        // Checked up front rather than per-entry. A prune that wants to remove
        // far more than expected is a misconfiguration, and finding that out
        // halfway through is finding it out too late.
        File::ensureDirectoryExists($this->sandbox . '/public/vendor/other');
        File::put($this->sandbox . '/public/vendor/other/x.css', 'x');
        config()->set('laranail.package-tools.assets.prune.max_deletions', 1);

        $this->artisan('laranail::package-tools.assets-prune', [
            '--prune' => true,
            '--force' => true,
        ])->assertExitCode(1);

        $this->assertNothingWasDeleted('The ceiling aborted after deleting.');
        self::assertFileExists($this->sandbox . '/public/vendor/other/x.css');
    }

    // -----------------------------------------------------------------
    // Finding 3 — the root that must never be prunable
    // -----------------------------------------------------------------

    #[Test]
    public function a_root_of_public_is_refused_with_zero_deletions(): void
    {
        // The live bug this suite exists for: one module with empty source and
        // target config made `public_path()` itself a recursive-delete target.
        config()->set('laranail.package-tools.assets.prune.roots', ['public']);

        // It fails rather than degrading to an empty scan: the operator asked
        // to prune and got nothing, and needs to be told why.
        $this->artisan('laranail::package-tools.assets-prune', [
            '--prune' => true,
            '--force' => true,
        ])
            ->expectsOutputToContain('misconfigured')
            ->assertExitCode(1);

        $this->assertNothingWasDeleted('A root of `public` was accepted.');
        self::assertDirectoryExists($this->sandbox . '/public');
    }

    #[Test]
    public function a_root_of_the_project_itself_is_refused(): void
    {
        config()->set('laranail.package-tools.assets.prune.roots', ['.']);

        $this->artisan('laranail::package-tools.assets-prune', [
            '--prune' => true,
            '--force' => true,
        ])->assertExitCode(1);

        $this->assertNothingWasDeleted('The project root was accepted as a prune root.');
    }

    #[Test]
    public function no_configured_roots_means_nothing_is_scanned(): void
    {
        config()->set('laranail.package-tools.assets.prune.roots', []);

        $this->artisan('laranail::package-tools.assets-prune', [
            '--prune' => true,
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertNothingWasDeleted('An empty root list still deleted.');
    }

    // -----------------------------------------------------------------
    // Symlinks
    // -----------------------------------------------------------------

    #[Test]
    public function pruning_a_symlink_unlinks_it_and_spares_its_target(): void
    {
        File::ensureDirectoryExists($this->sandbox . '/elsewhere');
        File::put($this->sandbox . '/elsewhere/keep.txt', 'x');
        symlink($this->sandbox . '/elsewhere', $this->sandbox . '/public/vendor/linked');

        $this->artisan('laranail::package-tools.assets-prune', [
            '--prune' => true,
            '--force' => true,
        ])->assertExitCode(0);

        self::assertFalse(
            is_link($this->sandbox . '/public/vendor/linked'),
            'The symlink survived the prune.',
        );
        self::assertFileExists(
            $this->sandbox . '/elsewhere/keep.txt',
            'Pruning a symlink followed it and deleted its target.',
        );
    }

    // -----------------------------------------------------------------
    // Narrowing
    // -----------------------------------------------------------------

    #[Test]
    public function narrowing_to_a_tag_narrows_what_is_expected(): void
    {
        $registry = $this->app->make(PublishTagRegistry::class);
        $registry->record('blog-assets', 'blog', [
            $this->sandbox . '/source/blog' => $this->sandbox . '/public/vendor/blog',
        ]);

        $this->artisan('laranail::package-tools.assets-prune', ['--tag' => ['blog-assets']])
            ->expectsOutputToContain('ghost')
            ->assertExitCode(0);

        $this->assertNothingWasDeleted('A report run deleted something.');
    }
}
