<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Services\Asset;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\Package\Tools\Services\Asset\PublishTagRegistry;

final class PublishTagRegistryTest extends TestCase
{
    private PublishTagRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new PublishTagRegistry;
    }

    #[Test]
    public function it_records_and_returns_an_entry(): void
    {
        $this->registry->record('blog-config', 'blog', ['/src/config.php' => '/app/config/blog.php']);

        $entry = $this->registry->get('blog-config');

        $this->assertNotNull($entry);
        $this->assertSame('blog-config', $entry->tag);
        $this->assertSame('blog', $entry->package);
        $this->assertSame(['/app/config/blog.php'], $entry->destinations());
        $this->assertSame(['/src/config.php'], $entry->sources());
        $this->assertFalse($entry->cleanable);
    }

    #[Test]
    public function an_unknown_tag_returns_null(): void
    {
        $this->assertNull($this->registry->get('never-recorded'));
        $this->assertFalse($this->registry->has('never-recorded'));
        $this->assertFalse($this->registry->isCleanable('never-recorded'));
    }

    #[Test]
    public function recording_the_same_tag_twice_merges_its_paths(): void
    {
        $this->registry->record('blog-assets', 'blog', ['/src/css' => '/public/vendor/blog/css']);
        $this->registry->record('blog-assets', 'blog', ['/src/js' => '/public/vendor/blog/js']);

        $entry = $this->registry->get('blog-assets');

        $this->assertNotNull($entry);
        $this->assertSame(
            ['/public/vendor/blog/css', '/public/vendor/blog/js'],
            $entry->destinations(),
        );
    }

    #[Test]
    public function cleanable_is_sticky_across_repeat_records(): void
    {
        $this->registry->record('blog-assets', 'blog', ['/a' => '/x'], cleanable: true);
        $this->registry->record('blog-assets', 'blog', ['/b' => '/y'], cleanable: false);

        $this->assertTrue(
            $this->registry->isCleanable('blog-assets'),
            'One call site asking for a clean must be enough.',
        );
    }

    #[Test]
    public function it_filters_by_package(): void
    {
        $this->registry->record('blog-config', 'blog', ['/a' => '/x']);
        $this->registry->record('blog-views', 'blog', ['/b' => '/y']);
        $this->registry->record('shop-config', 'shop', ['/c' => '/z']);

        $this->assertSame(['blog-config', 'blog-views'], array_keys($this->registry->forPackage('blog')));
        $this->assertSame(['shop-config'], array_keys($this->registry->forPackage('shop')));
        $this->assertSame([], $this->registry->forPackage('unknown'));
    }

    #[Test]
    public function it_lists_tags_and_packages(): void
    {
        $this->registry->record('blog-config', 'blog', ['/a' => '/x']);
        $this->registry->record('blog-views', 'blog', ['/b' => '/y']);
        $this->registry->record('shop-config', 'shop', ['/c' => '/z']);

        $this->assertSame(['blog-config', 'blog-views', 'shop-config'], $this->registry->tags());
        $this->assertSame(['blog', 'shop'], $this->registry->packages());
    }

    #[Test]
    public function it_filters_cleanable_entries(): void
    {
        $this->registry->record('a', 'p', ['/a' => '/x'], cleanable: true);
        $this->registry->record('b', 'p', ['/b' => '/y']);

        $this->assertSame(['a'], array_keys($this->registry->cleanable()));
    }

    #[Test]
    public function it_forgets_and_flushes(): void
    {
        $this->registry->record('a', 'p', ['/a' => '/x']);
        $this->registry->record('b', 'p', ['/b' => '/y']);

        $this->registry->forget('a');
        $this->assertSame(['b'], $this->registry->tags());

        $this->registry->flush();
        $this->assertSame([], $this->registry->all());
    }

    #[Test]
    public function duplicate_destinations_are_reported_once(): void
    {
        $this->registry->record('a', 'p', ['/src/one' => '/public/vendor/p', '/src/two' => '/public/vendor/p']);

        $entry = $this->registry->get('a');

        $this->assertNotNull($entry);
        $this->assertSame(['/public/vendor/p'], $entry->destinations());
        $this->assertSame(['/src/one', '/src/two'], $entry->sources());
    }
}
