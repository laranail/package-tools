<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Services\Asset;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use Simtabi\Laranail\Package\Tools\Exceptions\UnsafeAssetPath;
use Simtabi\Laranail\Package\Tools\Services\Asset\PublishRoot;

final class PublishRootTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = sys_get_temp_dir() . '/laranail-root-' . bin2hex(random_bytes(6));
        mkdir($this->base . '/public/vendor', 0o755, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->base));

        parent::tearDown();
    }

    /** @return array<string, array{0: string}> */
    public static function deniedRoots(): array
    {
        $cases = [];

        foreach (PublishRoot::DENY as $denied) {
            $cases[$denied] = [$denied];
        }

        return $cases;
    }

    #[Test]
    public function it_accepts_a_directory_inside_the_project(): void
    {
        $root = PublishRoot::make('public/vendor', $this->base);

        self::assertSame(PublishRoot::normalise($this->base . '/public/vendor'), $root->path());
        self::assertSame(2, $root->depth());
    }

    #[Test]
    public function a_root_that_does_not_exist_yet_is_still_valid(): void
    {
        // Validation is lexical on purpose: a root that has never been
        // published to is legitimate, and realpath() would reject it.
        $root = PublishRoot::make('public/not-yet', $this->base);

        self::assertStringEndsWith('public/not-yet', $root->path());
    }

    #[Test]
    public function an_absolute_path_inside_the_project_is_accepted(): void
    {
        self::assertNotNull(PublishRoot::make($this->base . '/public/vendor', $this->base));
    }

    #[Test]
    public function an_empty_root_is_refused(): void
    {
        $this->expectException(UnsafeAssetPath::class);
        $this->expectExceptionCode(5001);

        PublishRoot::make('   ', $this->base);
    }

    #[Test]
    public function a_null_byte_is_refused(): void
    {
        $this->expectException(UnsafeAssetPath::class);
        $this->expectExceptionCode(5002);

        PublishRoot::make("public/vendor\0/etc", $this->base);
    }

    #[Test]
    public function a_path_outside_the_project_is_refused(): void
    {
        $this->expectException(UnsafeAssetPath::class);
        $this->expectExceptionCode(5003);

        PublishRoot::make('/etc', $this->base);
    }

    #[Test]
    public function a_traversal_escaping_the_project_is_refused(): void
    {
        $this->expectException(UnsafeAssetPath::class);
        $this->expectExceptionCode(5003);

        PublishRoot::make('public/vendor/../../../../etc', $this->base);
    }

    #[Test]
    public function the_project_root_itself_is_refused(): void
    {
        $this->expectException(UnsafeAssetPath::class);
        $this->expectExceptionCode(5004);

        PublishRoot::make('.', $this->base);
    }

    /**
     * The single most likely typo is dropping `/vendor` off `public/vendor`.
     */
    #[Test]
    #[DataProvider('deniedRoots')]
    public function a_deny_listed_directory_is_refused(string $root): void
    {
        $this->expectException(UnsafeAssetPath::class);

        PublishRoot::make($root, $this->base);
    }

    #[Test]
    public function a_root_shallower_than_the_minimum_is_refused(): void
    {
        $this->expectException(UnsafeAssetPath::class);
        $this->expectExceptionCode(5005);

        PublishRoot::make('assets', $this->base, minimumDepth: 2);
    }

    #[Test]
    public function a_shallow_root_is_allowed_when_the_minimum_permits_it(): void
    {
        self::assertSame(1, PublishRoot::make('assets', $this->base, minimumDepth: 1)->depth());
    }

    // -----------------------------------------------------------------
    // contains()
    // -----------------------------------------------------------------

    #[Test]
    public function a_sibling_with_a_shared_prefix_is_not_contained(): void
    {
        // Without the trailing separator, str_starts_with would match this and
        // put a completely unrelated directory in deletion range.
        $root = PublishRoot::make('public/vendor', $this->base);

        self::assertFalse($root->contains($this->base . '/public/vendor2/thing'));
        self::assertTrue($root->contains($this->base . '/public/vendor/thing'));
    }

    #[Test]
    public function the_root_itself_is_not_contained(): void
    {
        $root = PublishRoot::make('public/vendor', $this->base);

        self::assertFalse($root->contains($this->base . '/public/vendor'));
    }

    #[Test]
    public function a_traversal_out_of_the_root_is_not_contained(): void
    {
        $root = PublishRoot::make('public/vendor', $this->base);

        self::assertFalse($root->contains($this->base . '/public/vendor/../../../etc/passwd'));
    }

    #[Test]
    public function a_deeply_nested_path_is_contained(): void
    {
        $root = PublishRoot::make('public/vendor', $this->base);

        self::assertTrue($root->contains($this->base . '/public/vendor/a/b/c/d.css'));
    }

    // -----------------------------------------------------------------
    // Symlinks
    // -----------------------------------------------------------------

    #[Test]
    public function a_root_reached_through_a_symlink_that_stays_inside_is_accepted(): void
    {
        // Rejecting every symlink would fail on macOS, where the system temp
        // directory is itself reached through one.
        mkdir($this->base . '/public/real', 0o755, true);
        symlink($this->base . '/public/real', $this->base . '/public/linked');

        self::assertNotNull(PublishRoot::make('public/linked', $this->base));
    }

    #[Test]
    public function a_root_whose_symlink_resolves_outside_the_project_is_refused(): void
    {
        $outside = sys_get_temp_dir() . '/laranail-outside-' . bin2hex(random_bytes(6));
        mkdir($outside, 0o755, true);
        symlink($outside, $this->base . '/public/escape');

        try {
            $this->expectException(UnsafeAssetPath::class);

            PublishRoot::make('public/escape', $this->base);
        } finally {
            exec('rm -rf ' . escapeshellarg($outside));
        }
    }

    // -----------------------------------------------------------------
    // normalise()
    // -----------------------------------------------------------------

    #[Test]
    public function it_collapses_dot_segments_without_the_filesystem(): void
    {
        self::assertSame('/a/c', PublishRoot::normalise('/a/b/../c'));
        self::assertSame('/a/b', PublishRoot::normalise('/a/./b/'));
        self::assertSame('/a/b', PublishRoot::normalise('/a//b'));
        self::assertSame('/', PublishRoot::normalise('/'));
    }
}
