<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Services\Asset;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\ServiceProvider;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;
use Simtabi\Laranail\Package\Tools\Services\Asset\OrphanEntry;
use Simtabi\Laranail\Package\Tools\Services\Asset\PublishRoot;
use Simtabi\Laranail\Package\Tools\Services\Asset\OrphanScanner;
use Simtabi\Laranail\Package\Tools\Services\Asset\PublishPathGuard;
use Simtabi\Laranail\Package\Tools\Services\Asset\PublishTagRegistry;

final class OrphanScannerTest extends TestCase
{
    private string $sandbox;

    /** @var array<string, mixed> */
    private array $publishGroupsBackup = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Process-wide static. Without a snapshot these tests decide each
        // other's expected sets.
        $this->publishGroupsBackup = ServiceProvider::$publishGroups;
        ServiceProvider::$publishGroups = [];

        $this->sandbox = sys_get_temp_dir() . '/laranail-orphan-' . bin2hex(random_bytes(6));

        File::ensureDirectoryExists($this->sandbox . '/source/blog');
        File::ensureDirectoryExists($this->sandbox . '/public/vendor');

        File::put($this->sandbox . '/source/blog/app.css', 'body{}');
    }

    protected function tearDown(): void
    {
        ServiceProvider::$publishGroups = $this->publishGroupsBackup;
        File::deleteDirectory($this->sandbox);

        parent::tearDown();
    }

    // -----------------------------------------------------------------
    // The set difference
    // -----------------------------------------------------------------

    #[Test]
    public function a_published_directory_is_not_an_orphan(): void
    {
        File::ensureDirectoryExists($this->sandbox . '/public/vendor/blog');
        File::put($this->sandbox . '/public/vendor/blog/app.css', 'body{}');

        ServiceProvider::$publishGroups['blog'] = [
            $this->sandbox . '/source/blog' => $this->sandbox . '/public/vendor/blog',
        ];

        self::assertTrue($this->scanner()->scan()->isEmpty());
    }

    #[Test]
    public function a_directory_nothing_publishes_is_an_orphan(): void
    {
        File::ensureDirectoryExists($this->sandbox . '/public/vendor/ghost');
        File::put($this->sandbox . '/public/vendor/ghost/old.css', 'x');

        $report = $this->scanner()->scan();

        self::assertSame(['ghost'], $this->relativePaths($report->entries));
        self::assertSame(1, $report->fileCount);
    }

    #[Test]
    public function a_stale_file_inside_a_published_directory_is_found(): void
    {
        // The case a whole-directory diff misses: the directory is still
        // published, but this one file inside it is not published any more.
        File::ensureDirectoryExists($this->sandbox . '/public/vendor/blog');
        File::put($this->sandbox . '/public/vendor/blog/app.css', 'body{}');
        File::put($this->sandbox . '/public/vendor/blog/removed.css', 'stale');

        ServiceProvider::$publishGroups['blog'] = [
            $this->sandbox . '/source/blog' => $this->sandbox . '/public/vendor/blog',
        ];

        self::assertSame(['blog/removed.css'], $this->relativePaths($this->scanner()->scan()->entries));
    }

    #[Test]
    public function every_publish_group_counts_not_only_laranails(): void
    {
        // The correctness hinge. A scan that only knew about the tags this
        // package registered would report Livewire's and Horizon's asset
        // directories as orphans and offer to delete them.
        File::ensureDirectoryExists($this->sandbox . '/source/livewire');
        File::put($this->sandbox . '/source/livewire/livewire.js', 'x');
        File::ensureDirectoryExists($this->sandbox . '/public/vendor/livewire');
        File::put($this->sandbox . '/public/vendor/livewire/livewire.js', 'x');

        ServiceProvider::$publishGroups['livewire:assets'] = [
            $this->sandbox . '/source/livewire' => $this->sandbox . '/public/vendor/livewire',
        ];

        // An empty registry — laranail registered nothing at all here.
        self::assertTrue($this->scanner()->scan()->isEmpty());
    }

    #[Test]
    public function a_registry_destination_absent_from_the_static_still_counts(): void
    {
        File::ensureDirectoryExists($this->sandbox . '/public/vendor/blog');
        File::put($this->sandbox . '/public/vendor/blog/app.css', 'body{}');

        $registry = new PublishTagRegistry;
        $registry->record('blog', 'blog', [
            $this->sandbox . '/source/blog' => $this->sandbox . '/public/vendor/blog',
        ]);

        self::assertTrue($this->scanner($registry)->scan()->isEmpty());
    }

    #[Test]
    public function a_deleted_source_makes_its_destination_an_orphan(): void
    {
        // The case this whole scanner exists for: the package is gone, so it
        // registers nothing, so its files have nobody to claim them.
        File::ensureDirectoryExists($this->sandbox . '/public/vendor/uninstalled');
        File::put($this->sandbox . '/public/vendor/uninstalled/app.css', 'x');

        self::assertSame(
            ['uninstalled'],
            $this->relativePaths($this->scanner()->scan()->entries),
        );
    }

    // -----------------------------------------------------------------
    // Narrowing
    // -----------------------------------------------------------------

    #[Test]
    public function narrowing_to_a_tag_narrows_the_expected_set(): void
    {
        File::ensureDirectoryExists($this->sandbox . '/public/vendor/blog');
        File::put($this->sandbox . '/public/vendor/blog/app.css', 'body{}');
        File::ensureDirectoryExists($this->sandbox . '/public/vendor/shop');
        File::put($this->sandbox . '/public/vendor/shop/app.css', 'x');

        ServiceProvider::$publishGroups['blog'] = [
            $this->sandbox . '/source/blog' => $this->sandbox . '/public/vendor/blog',
        ];
        ServiceProvider::$publishGroups['shop'] = [
            $this->sandbox . '/source/blog' => $this->sandbox . '/public/vendor/shop',
        ];

        self::assertTrue($this->scanner()->scan()->isEmpty());

        // With only `blog` expected, shop's tree falls out.
        self::assertSame(
            ['shop'],
            $this->relativePaths($this->scanner()->scan(['blog'])->entries),
        );
    }

    // -----------------------------------------------------------------
    // Collapsing
    // -----------------------------------------------------------------

    #[Test]
    public function a_wholly_orphaned_directory_collapses_to_one_entry(): void
    {
        File::ensureDirectoryExists($this->sandbox . '/public/vendor/ghost/deep/deeper');
        File::put($this->sandbox . '/public/vendor/ghost/a.css', 'x');
        File::put($this->sandbox . '/public/vendor/ghost/deep/b.css', 'x');
        File::put($this->sandbox . '/public/vendor/ghost/deep/deeper/c.css', 'x');

        $report = $this->scanner()->scan();

        self::assertSame(['ghost'], $this->relativePaths($report->entries));
        self::assertTrue($report->entries[0]->isDirectory);
        self::assertSame(3, $report->fileCount, 'The collapsed entry should still account for its files.');
        self::assertSame(3, $report->bytes);
    }

    #[Test]
    public function a_directory_with_a_still_published_descendant_does_not_collapse(): void
    {
        File::ensureDirectoryExists($this->sandbox . '/public/vendor/blog/keep');
        File::put($this->sandbox . '/public/vendor/blog/keep/app.css', 'body{}');
        File::ensureDirectoryExists($this->sandbox . '/public/vendor/blog/drop');
        File::put($this->sandbox . '/public/vendor/blog/drop/old.css', 'x');

        ServiceProvider::$publishGroups['blog'] = [
            $this->sandbox . '/source/blog/app.css' => $this->sandbox . '/public/vendor/blog/keep/app.css',
        ];

        self::assertSame(
            ['blog/drop'],
            $this->relativePaths($this->scanner()->scan()->entries),
            'The parent of a still-published file must not be reported, or the whole branch goes.',
        );
    }

    // -----------------------------------------------------------------
    // Symlinks
    // -----------------------------------------------------------------

    #[Test]
    public function a_symlinked_directory_is_a_leaf_and_is_never_descended(): void
    {
        // Descending would report the contents of somewhere outside the root
        // as orphaned, and then offer to delete them.
        File::ensureDirectoryExists($this->sandbox . '/outside/secrets');
        File::put($this->sandbox . '/outside/secrets/key.pem', 'x');
        symlink($this->sandbox . '/outside/secrets', $this->sandbox . '/public/vendor/linked');

        $report = $this->scanner()->scan();

        self::assertSame(['linked'], $this->relativePaths($report->entries));
        self::assertFalse(
            $report->entries[0]->isDirectory,
            'A symlink is reported as a leaf, so deleting it unlinks rather than recursing.',
        );
    }

    // -----------------------------------------------------------------
    // Honesty about what it saw
    // -----------------------------------------------------------------

    #[Test]
    public function hitting_the_depth_ceiling_is_reported(): void
    {
        File::ensureDirectoryExists($this->sandbox . '/public/vendor/a/b/c/d');
        File::put($this->sandbox . '/public/vendor/a/b/c/d/deep.css', 'x');

        $report = $this->scanner(maxDepth: 2)->scan();

        self::assertTrue(
            $report->truncated,
            'A scan that could not see the whole tree must say so, or it reads exactly like a clean one.',
        );
    }

    #[Test]
    public function an_empty_root_produces_an_empty_report(): void
    {
        $report = $this->scanner()->scan();

        self::assertTrue($report->isEmpty());
        self::assertFalse($report->truncated);
        self::assertSame(0, $report->bytes);
    }

    #[Test]
    public function a_guard_with_no_roots_scans_nothing(): void
    {
        $scanner = new OrphanScanner(new PublishPathGuard, new PublishTagRegistry);

        self::assertTrue($scanner->scan()->isEmpty());
        self::assertSame([], $scanner->scan()->rootsScanned);
    }

    #[Test]
    public function an_orphan_is_attributed_to_the_tag_whose_destination_it_sits_under(): void
    {
        File::ensureDirectoryExists($this->sandbox . '/public/vendor/blog/stale');
        File::put($this->sandbox . '/public/vendor/blog/stale/old.css', 'x');

        $registry = new PublishTagRegistry;
        $registry->record('blog-assets', 'blog', [
            $this->sandbox . '/source/blog' => $this->sandbox . '/public/vendor/blog',
        ]);

        // The registry claims `blog`, so `blog/stale` is attributed to it —
        // but `source/blog` holds only app.css, so `stale` is still an orphan.
        $entries = $this->scanner($registry)->scan()->entries;

        self::assertSame(['blog/stale'], $this->relativePaths($entries));
        self::assertSame('blog-assets', $entries[0]->attributedTag);
    }

    private function guard(): PublishPathGuard
    {
        return new PublishPathGuard([
            PublishRoot::make('public/vendor', $this->sandbox),
        ]);
    }

    private function scanner(?PublishTagRegistry $registry = null, int $maxDepth = 12): OrphanScanner
    {
        return new OrphanScanner($this->guard(), $registry ?? new PublishTagRegistry, $maxDepth);
    }

    /** @return list<string> */
    private function relativePaths(iterable $entries): array
    {
        $paths = [];

        foreach ($entries as $entry) {
            self::assertInstanceOf(OrphanEntry::class, $entry);
            $paths[] = $entry->relativePath;
        }

        sort($paths);

        return $paths;
    }
}
