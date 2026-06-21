<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Services;

use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Package\Tools\Concerns\Package\HasAssetCleanup;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

/**
 * Bug 2: cleanupAllAssets() iterates the registry through getRegistered(), and
 * cleanupAssets() returns a real bool (the underlying registry cleanup() is
 * void).
 */
class HasAssetCleanupTest extends TestCase
{
    use HasAssetCleanup;

    #[Test]
    public function cleanup_assets_returns_true_for_a_registered_tag(): void
    {
        $this->withAssetCleanup();
        $this->registerAssetForCleanup('/tmp/does-not-exist-laranail', 'blog-assets');

        $this->assertTrue($this->cleanupAssets('blog-assets'));
    }

    #[Test]
    public function cleanup_assets_returns_false_for_an_unknown_tag(): void
    {
        $this->assertFalse($this->cleanupAssets('never-registered'));
    }

    #[Test]
    public function cleanup_all_assets_reports_a_result_per_registered_tag(): void
    {
        $this->withAssetCleanup();
        $this->registerAssetForCleanup('/tmp/does-not-exist-laranail-a', 'tag-a');
        $this->registerAssetForCleanup('/tmp/does-not-exist-laranail-b', 'tag-b');

        $results = $this->cleanupAllAssets();

        $this->assertArrayHasKey('tag-a', $results);
        $this->assertArrayHasKey('tag-b', $results);
        $this->assertTrue($results['tag-a']);
        $this->assertTrue($results['tag-b']);
    }
}
