<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Services\Asset;

use Illuminate\Config\Repository;
use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Package\Tools\Exceptions\UnsafeAssetPath;
use Simtabi\Laranail\Package\Tools\Services\Asset\PublishPathGuard;
use Simtabi\Laranail\Package\Tools\Services\Asset\PublishRoot;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

/**
 * The one class in this package that deletes anything, so every refusal is
 * asserted and every deletion is asserted to have deleted only what it should.
 */
final class PublishPathGuardTest extends TestCase
{
    private string $base;

    private string $vendor;

    private PublishPathGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = sys_get_temp_dir() . '/laranail-guard-' . bin2hex(random_bytes(6));
        $this->vendor = $this->base . '/public/vendor';

        mkdir($this->vendor . '/blog/css', 0o755, true);
        mkdir($this->base . '/config', 0o755, true);
        mkdir($this->base . '/precious', 0o755, true);

        file_put_contents($this->vendor . '/blog/css/app.css', 'body{}');
        file_put_contents($this->vendor . '/.gitignore', '*');
        file_put_contents($this->base . '/config/app.php', '<?php return [];');
        file_put_contents($this->base . '/precious/keep.txt', 'do not delete');

        $this->guard = new PublishPathGuard(
            [PublishRoot::make('public/vendor', $this->base)],
            ['.gitignore', '.gitkeep'],
        );
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->base));

        parent::tearDown();
    }

    // -----------------------------------------------------------------
    // What it deletes
    // -----------------------------------------------------------------

    #[Test]
    public function it_deletes_a_file_inside_a_root(): void
    {
        self::assertTrue($this->guard->delete($this->vendor . '/blog/css/app.css'));
        self::assertFileDoesNotExist($this->vendor . '/blog/css/app.css');
    }

    #[Test]
    public function it_deletes_a_directory_inside_a_root(): void
    {
        self::assertTrue($this->guard->delete($this->vendor . '/blog'));
        self::assertDirectoryDoesNotExist($this->vendor . '/blog');
    }

    #[Test]
    public function deleting_something_already_gone_succeeds(): void
    {
        self::assertTrue($this->guard->delete($this->vendor . '/never-existed'));
    }

    // -----------------------------------------------------------------
    // What it refuses
    // -----------------------------------------------------------------

    #[Test]
    public function it_refuses_a_path_outside_every_root(): void
    {
        $this->expectException(UnsafeAssetPath::class);
        $this->expectExceptionCode(5006);

        $this->guard->delete($this->base . '/config/app.php');
    }

    #[Test]
    public function refusing_leaves_the_file_alone(): void
    {
        try {
            $this->guard->delete($this->base . '/config/app.php');
        } catch (UnsafeAssetPath) {
            // expected
        }

        self::assertFileExists($this->base . '/config/app.php');
    }

    #[Test]
    public function it_refuses_the_root_itself(): void
    {
        // Contents are deletable; the root is not.
        $this->expectException(UnsafeAssetPath::class);
        $this->expectExceptionCode(5008);

        $this->guard->delete($this->vendor);
    }

    #[Test]
    public function it_refuses_a_traversal_out_of_the_root(): void
    {
        $this->expectException(UnsafeAssetPath::class);

        $this->guard->delete($this->vendor . '/../../config/app.php');
    }

    #[Test]
    public function it_refuses_a_protected_basename(): void
    {
        $this->expectException(UnsafeAssetPath::class);
        $this->expectExceptionCode(5009);

        $this->guard->delete($this->vendor . '/.gitignore');
    }

    #[Test]
    public function a_guard_with_no_roots_refuses_everything(): void
    {
        // Fails closed. An empty root list is far more likely to be a
        // misconfiguration than an instruction to delete from anywhere.
        $empty = new PublishPathGuard;

        self::assertFalse($empty->isDeletable($this->vendor . '/blog/css/app.css'));

        $this->expectException(UnsafeAssetPath::class);
        $empty->delete($this->vendor . '/blog/css/app.css');
    }

    #[Test]
    public function it_refuses_an_empty_or_null_byte_path(): void
    {
        self::assertFalse($this->guard->isDeletable(''));
        self::assertFalse($this->guard->isDeletable($this->vendor . "/a\0b"));
    }

    // -----------------------------------------------------------------
    // Symlinks — the cases that actually lose data
    // -----------------------------------------------------------------

    #[Test]
    public function deleting_a_symlinked_file_unlinks_it_and_spares_the_target(): void
    {
        symlink($this->base . '/precious/keep.txt', $this->vendor . '/linked.txt');

        // Allowed even though it resolves outside the root, because deleting it
        // means unlink(), which never touches the target. Refusing would leave a
        // stray link in a publish root permanently — every route to removing it
        // goes through this same check.
        self::assertTrue($this->guard->isDeletable($this->vendor . '/linked.txt'));

        $this->guard->delete($this->vendor . '/linked.txt');

        self::assertFalse(is_link($this->vendor . '/linked.txt'));
        self::assertFileExists($this->base . '/precious/keep.txt');
    }

    #[Test]
    public function deleting_a_symlinked_directory_never_recurses_into_the_target(): void
    {
        // The one that loses data: deleteDirectory() on a link would empty
        // somewhere the guard never approved. delete() dispatches on is_link()
        // first, so the link goes and the directory it pointed at does not.
        symlink($this->base . '/precious', $this->vendor . '/linked-dir');

        $this->guard->delete($this->vendor . '/linked-dir');

        self::assertFalse(is_link($this->vendor . '/linked-dir'));
        self::assertDirectoryExists($this->base . '/precious');
        self::assertFileExists($this->base . '/precious/keep.txt');
    }

    #[Test]
    public function a_path_under_a_symlinked_parent_pointing_outside_is_refused(): void
    {
        symlink($this->base . '/precious', $this->vendor . '/swapped');

        self::assertFalse($this->guard->isDeletable($this->vendor . '/swapped/keep.txt'));
        self::assertFileExists($this->base . '/precious/keep.txt');
    }

    #[Test]
    public function a_symlink_staying_inside_the_root_is_still_deletable(): void
    {
        // Rejecting every symlink would be simpler and wrong: it fails on macOS,
        // where the temp directory is itself reached through one.
        mkdir($this->vendor . '/target', 0o755, true);
        file_put_contents($this->vendor . '/target/in.txt', 'x');
        symlink($this->vendor . '/target', $this->vendor . '/inside-link');

        self::assertTrue($this->guard->isDeletable($this->vendor . '/inside-link'));

        $this->guard->delete($this->vendor . '/inside-link');

        self::assertFileExists($this->vendor . '/target/in.txt', 'The link was followed instead of unlinked.');
        self::assertFalse(is_link($this->vendor . '/inside-link'));
    }

    // -----------------------------------------------------------------
    // Roots
    // -----------------------------------------------------------------

    #[Test]
    public function it_reports_which_root_owns_a_path(): void
    {
        self::assertNotNull($this->guard->rootFor($this->vendor . '/blog'));
        self::assertNull($this->guard->rootFor($this->base . '/config/app.php'));
    }

    #[Test]
    public function it_builds_from_config(): void
    {
        $guard = PublishPathGuard::fromConfig(new Repository([
            'package-tools' => ['assets' => ['prune' => ['roots' => ['public/vendor']]]],
        ]), $this->base);

        self::assertCount(1, $guard->roots());
        self::assertTrue($guard->isDeletable($this->vendor . '/blog'));
    }

    #[Test]
    public function a_misconfigured_root_fails_loudly_at_build_time(): void
    {
        // Finding 3: the whole document root, one dropped path segment away.
        $this->expectException(UnsafeAssetPath::class);

        PublishPathGuard::fromConfig(new Repository([
            'package-tools' => ['assets' => ['prune' => ['roots' => ['public']]]],
        ]), $this->base);
    }

    #[Test]
    public function config_with_no_prune_block_defaults_to_public_vendor(): void
    {
        $guard = PublishPathGuard::fromConfig(new Repository([]), $this->base);

        self::assertCount(1, $guard->roots());
        self::assertStringEndsWith('public/vendor', $guard->roots()[0]->path());
    }
}
