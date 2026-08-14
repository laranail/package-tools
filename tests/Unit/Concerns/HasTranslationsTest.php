<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Concerns;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\Package\Tools\Package;

/**
 * Tests for HasTranslations concern
 */
class HasTranslationsTest extends TestCase
{
    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();
        $this->package = new Package;
        $this->package->setName('test-vendor/test-package');
    }

    #[Test]
    public function it_can_register_translations(): void
    {
        $result = $this->package->hasTranslations();

        $this->assertSame($this->package, $result, 'Should support fluent chaining');
        $this->assertTrue($this->package->hasTranslations);
    }

    #[Test]
    public function it_uses_default_translations_directory(): void
    {
        $this->package->setPathFrom('/var/www/package')->hasTranslations();

        $this->assertTrue($this->package->hasTranslations);
        $this->assertSame('resources/lang', Package::LANG_DIR);
    }

    #[Test]
    public function the_translation_namespace_is_vendor_scoped_with_a_hyphen(): void
    {
        // A slash would nest the published files under lang/vendor/{vendor}/{package},
        // one level deeper than vendor:publish and consumer overrides expect.
        $this->assertSame('test-vendor-test-package', $this->package->translationNamespace());
    }
}
