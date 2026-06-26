<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

/**
 * HasConfigNamespaceCachingTest - memoization of computed namespaces.
 */
class HasConfigNamespaceCachingTest extends TestCase
{
    protected function makePackage(string $name = 'acme/widget'): Package
    {
        return (new Package)->setName($name);
    }

    #[Test]
    public function it_computes_all_four_namespace_formats(): void
    {
        $package = $this->makePackage();

        $this->assertSame('acme.widget', $package->getDottedNamespace());
        $this->assertSame('acme-widget', $package->getDashedNamespace());
        $this->assertSame('acme::widget', $package->getDoubleColonNamespace());
        $this->assertSame('acme/widget', $package->getSlashNamespace());
    }

    #[Test]
    public function it_resolves_namespaced_config_key_and_publish_tag(): void
    {
        $package = $this->makePackage();

        $this->assertSame('acme.widget', $package->getNamespacedConfigKey('widget'));
        $this->assertSame('acme::widget-config', $package->getNamespacedPublishTag());
        $this->assertSame('acme::widget-views', $package->getNamespacedPublishTag('views'));
    }

    #[Test]
    public function it_memoizes_a_namespace_after_first_read(): void
    {
        $package = $this->makePackage();

        $this->assertArrayNotHasKey('dotted', $package->getCachedNamespaces());

        $package->getDottedNamespace();

        $this->assertArrayHasKey('dotted', $package->getCachedNamespaces());
        $this->assertSame('acme.widget', $package->getCachedNamespaces()['dotted']);
    }

    #[Test]
    public function it_warms_all_four_namespace_keys(): void
    {
        $package = $this->makePackage();

        $package->warmNamespaceCache();

        $cached = $package->getCachedNamespaces();

        $this->assertArrayHasKey('dotted', $cached);
        $this->assertArrayHasKey('dashed', $cached);
        $this->assertArrayHasKey('doubleColon', $cached);
        $this->assertArrayHasKey('slash', $cached);
        $this->assertCount(4, $cached);
    }

    #[Test]
    public function it_clears_the_cache(): void
    {
        $package = $this->makePackage();

        $package->warmNamespaceCache();
        $this->assertNotEmpty($package->getCachedNamespaces());

        $package->clearNamespaceCache();

        $this->assertSame([], $package->getCachedNamespaces());
    }

    #[Test]
    public function it_invalidates_the_cache_on_rename(): void
    {
        $package = $this->makePackage('acme/widget');

        $this->assertSame('acme.widget', $package->getDottedNamespace());

        $package->setName('other/thing');

        $this->assertSame('other.thing', $package->getDottedNamespace());
    }

    #[Test]
    public function it_throws_on_null_vendor_and_does_not_cache_it(): void
    {
        // A freshly-constructed package has no vendor set.
        $package = new Package;

        $caught = false;
        try {
            $package->getDottedNamespace();
        } catch (RuntimeException) {
            $caught = true;
        }

        $this->assertTrue($caught, 'Expected a RuntimeException for a null vendor.');
        $this->assertArrayNotHasKey('dotted', $package->getCachedNamespaces());

        // Once a valid name is set, the getter resolves correctly.
        $package->setName('acme/widget');
        $this->assertSame('acme.widget', $package->getDottedNamespace());
    }
}
