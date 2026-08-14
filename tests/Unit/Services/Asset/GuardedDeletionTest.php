<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Services\Asset;

use Illuminate\Support\Facades\File;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Package\Tools\Providers\PackageToolsServiceProvider;
use Simtabi\Laranail\Package\Tools\Services\Asset\AssetRegistry;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

/**
 * The two delete paths that were bypassing the guard.
 *
 * `PublishPathGuard`'s docblock has always said it is the one place in this
 * package that deletes anything, and that it exists because a registered
 * destination of `''` resolves to the document root. Both claims were false:
 * `AssetRegistry::cleanup()` and `HasAssetPublisher::cleanAsset()` called
 * `File::deleteDirectory()` directly, with no containment check and no
 * `is_link()` dispatch.
 */
final class GuardedDeletionTest extends TestCase
{
    private string $sandbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandbox = sys_get_temp_dir() . '/laranail-guarded-' . bin2hex(random_bytes(6));

        File::ensureDirectoryExists($this->sandbox . '/public/vendor/blog');
        File::put($this->sandbox . '/public/vendor/blog/app.css', 'body{}');
        File::put($this->sandbox . '/public/index.php', '<?php // document root');
        File::ensureDirectoryExists($this->sandbox . '/config');
        File::put($this->sandbox . '/config/blog.php', '<?php return [];');

        $this->app->setBasePath($this->sandbox);
        config()->set('laranail.package-tools.assets.prune.roots', ['public/vendor']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sandbox);

        parent::tearDown();
    }

    /** @return array<int, class-string> */
    #[Override]
    protected function getPackageProviders($app): array
    {
        return [PackageToolsServiceProvider::class];
    }

    #[Test]
    public function it_deletes_a_target_inside_a_prune_root(): void
    {
        $registry = new AssetRegistry;
        $registry->register('blog', $this->sandbox . '/public/vendor/blog', shouldCleanup: true);

        self::assertSame([], $registry->cleanup('blog'));
        self::assertDirectoryDoesNotExist($this->sandbox . '/public/vendor/blog');
    }

    #[Test]
    public function it_refuses_a_target_outside_every_prune_root(): void
    {
        // A package publishes into config/ and database/migrations/ too.
        // Silently removing a published config file is a far worse surprise
        // than declining to.
        $registry = new AssetRegistry;
        $registry->register('cfg', $this->sandbox . '/config/blog.php', shouldCleanup: true);

        $refused = $registry->cleanup('cfg');

        self::assertSame([$this->sandbox . '/config/blog.php'], $refused);
        self::assertFileExists($this->sandbox . '/config/blog.php');
    }

    #[Test]
    public function it_refuses_the_document_root(): void
    {
        // The exact failure the guard was written for: an empty registered
        // destination resolves to public_path(''), the document root.
        $registry = new AssetRegistry;
        $registry->register('oops', $this->sandbox . '/public', shouldCleanup: true);

        $refused = $registry->cleanup('oops');

        self::assertSame([$this->sandbox . '/public'], $refused);
        self::assertFileExists($this->sandbox . '/public/index.php');
        self::assertDirectoryExists($this->sandbox . '/public');
    }

    #[Test]
    public function it_refuses_a_path_escaping_with_dot_dot(): void
    {
        $outside = $this->sandbox . '/public/vendor/../../config';

        $registry = new AssetRegistry;
        $registry->register('escape', $outside, shouldCleanup: true);

        self::assertNotSame([], $registry->cleanup('escape'));
        self::assertFileExists($this->sandbox . '/config/blog.php');
    }

    #[Test]
    public function a_symlink_is_unlinked_and_its_target_survives(): void
    {
        File::ensureDirectoryExists($this->sandbox . '/precious');
        File::put($this->sandbox . '/precious/keep.txt', 'x');
        symlink($this->sandbox . '/precious', $this->sandbox . '/public/vendor/linked');

        $registry = new AssetRegistry;
        $registry->register('link', $this->sandbox . '/public/vendor/linked', shouldCleanup: true);

        $registry->cleanup('link');

        self::assertFalse(is_link($this->sandbox . '/public/vendor/linked'));
        self::assertFileExists(
            $this->sandbox . '/precious/keep.txt',
            'deleteDirectory() followed the link and emptied its target.',
        );
    }

    #[Test]
    public function a_misconfigured_root_deletes_nothing(): void
    {
        // A guard with no usable roots refuses everything. Cleaning nothing
        // beats cleaning the wrong thing.
        config()->set('laranail.package-tools.assets.prune.roots', ['public']);

        $registry = new AssetRegistry;
        $registry->register('blog', $this->sandbox . '/public/vendor/blog', shouldCleanup: true);

        self::assertNotSame([], $registry->cleanup('blog'));
        self::assertFileExists($this->sandbox . '/public/vendor/blog/app.css');
    }
}
