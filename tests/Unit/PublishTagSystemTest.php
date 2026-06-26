<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

/**
 * PublishTagSystemTest - Test enhanced publish tag functionality
 */
class PublishTagSystemTest extends TestCase
{
    protected Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->package = new Package;
        $this->package->setName('test/package');
        $this->package->setPublishTagId('laranail');
    }

    #[Test]
    public function it_sets_and_gets_publish_tag_id(): void
    {
        $this->package->setPublishTagId('custom-id');

        $this->assertEquals('custom-id', $this->package->getPublishTagId());
    }

    #[Test]
    public function it_builds_basic_publish_tag(): void
    {
        $tag = $this->package->buildPublishTag('config');

        $this->assertEquals('laranail::config', $tag);
    }

    #[Test]
    public function it_builds_tag_with_default_separator(): void
    {
        $tag = $this->package->buildPublishTag('views');

        $this->assertEquals('laranail::views', $tag);
    }

    #[Test]
    public function it_builds_tag_with_colon_separator(): void
    {
        $tag = $this->package->buildPublishTag('migrations', ':');

        $this->assertEquals('laranail:migrations', $tag);
    }

    #[Test]
    public function it_builds_tag_with_dash_separator(): void
    {
        $tag = $this->package->buildPublishTag('assets', '-');

        $this->assertEquals('laranail-assets', $tag);
    }

    #[Test]
    public function it_supports_nested_tags(): void
    {
        $tag = $this->package->buildPublishTag('package::imani-tp');

        $this->assertEquals('laranail::package::imani-tp', $tag);
    }

    #[Test]
    public function it_normalizes_tag_names(): void
    {
        $tag = $this->package->buildPublishTag('  Config  ');

        $this->assertEquals('laranail::config', $tag);
    }

    #[Test]
    public function it_lowercases_tags(): void
    {
        $tag = $this->package->buildPublishTag('VIEWS');

        $this->assertEquals('laranail::views', $tag);
    }

    #[Test]
    public function it_rejects_invalid_separator(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid publish tag separator');

        $this->package->buildPublishTag('config', '|');
    }

    #[Test]
    public function it_rejects_empty_separator(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('separator cannot be empty');

        $this->package->buildPublishTag('config', '');
    }

    #[Test]
    public function it_rejects_invalid_tag_name_with_spaces(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid publish tag name');

        $this->package->buildPublishTag('config file');
    }

    #[Test]
    public function it_rejects_invalid_tag_name_with_special_chars(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid publish tag name');

        $this->package->buildPublishTag('config@file');
    }

    #[Test]
    public function it_caches_built_tags(): void
    {
        $this->package->buildPublishTag('config');
        $this->package->buildPublishTag('views');
        $this->package->buildPublishTag('assets');

        $cached = $this->package->getBuiltPublishTags();

        $this->assertCount(3, $cached);
        $this->assertContains('laranail::config', $cached);
        $this->assertContains('laranail::views', $cached);
        $this->assertContains('laranail::assets', $cached);
    }

    #[Test]
    public function it_clears_publish_tags_cache(): void
    {
        $this->package->buildPublishTag('config');
        $this->package->buildPublishTag('views');

        $this->assertCount(2, $this->package->getBuiltPublishTags());

        $this->package->clearPublishTagsCache();

        $this->assertCount(0, $this->package->getBuiltPublishTags());
    }

    #[Test]
    public function it_throws_exception_when_no_base_tag_set(): void
    {
        $package = new Package;
        $package->setName('test/package');
        // Don't set publish tag ID

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('You must set a publish tag ID');

        $package->buildPublishTag('config');
    }

    #[Test]
    public function it_allows_dashes_in_tag_names(): void
    {
        $tag = $this->package->buildPublishTag('blog-assets');

        $this->assertEquals('laranail::blog-assets', $tag);
    }

    #[Test]
    public function it_allows_colons_in_nested_tags(): void
    {
        $tag = $this->package->buildPublishTag('vendor::package::config');

        $this->assertEquals('laranail::vendor::package::config', $tag);
    }

    #[Test]
    public function it_removes_special_chars_from_start_and_end(): void
    {
        $tag = $this->package->buildPublishTag('@config!');

        $this->assertEquals('laranail::config', $tag);
    }

    #[Test]
    public function it_preserves_internal_structure(): void
    {
        $tag = $this->package->buildPublishTag('blog-config');

        $this->assertEquals('laranail::blog-config', $tag);
    }
}
