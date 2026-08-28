<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Concerns;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
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
    public function the_translation_namespace_is_the_composer_package_name(): void
    {
        // Laravel interpolates the namespace into the override path itself, so the slash simply
        // nests the published files at lang/vendor/{vendor}/{package} -- which is where
        // FileLoader::loadNamespaceOverrides() then reads them from. Grouping a vendor's packages
        // under one directory is the point rather than a hazard.
        $this->assertSame('test-vendor/test-package', $this->package->translationNamespace());
    }
}
