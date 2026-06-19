<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Tests;

/**
 * TestsPackageNamespacing - Trait for testing package namespace functionality
 *
 * **IMPROVEMENT #5: Testing Documentation & Helpers**
 *
 * Provides assertions specifically for testing namespace-related functionality
 * in packages that use config namespacing features.
 */
trait TestsPackageNamespacing
{
    /**
     * Assert all namespace formats match expected values
     *
     * @param string $expectedVendor Expected vendor name
     * @param string $expectedPackage Expected package name
     */
    protected function assertAllNamespaceFormats(string $expectedVendor, string $expectedPackage): void
    {
        $package = $this->getPackage();

        // Dotted format
        $this->assertEquals(
            "{$expectedVendor}.{$expectedPackage}",
            $package->getDottedNamespace(),
            'Dotted namespace format mismatch'
        );

        // Dashed format
        $this->assertEquals(
            "{$expectedVendor}-{$expectedPackage}",
            $package->getDashedNamespace(),
            'Dashed namespace format mismatch'
        );

        // Double-colon format
        $this->assertEquals(
            "{$expectedVendor}::{$expectedPackage}",
            $package->getDoubleColonNamespace(),
            'Double-colon namespace format mismatch'
        );

        // Slash format
        $this->assertEquals(
            "{$expectedVendor}/{$expectedPackage}",
            $package->getSlashNamespace(),
            'Slash namespace format mismatch'
        );
    }

    /**
     * Assert underscore namespace format
     *
     * @param string $expected Expected underscore format (e.g., 'vendor_package')
     */
    protected function assertUnderscoreNamespace(string $expected): void
    {
        $package = $this->getPackage();

        $this->assertEquals(
            $expected,
            $package->getUnderscoreNamespace(),
            'Underscore namespace format mismatch'
        );
    }

    /**
     * Assert camelCase namespace format
     *
     * @param string $expected Expected camelCase format (e.g., 'vendorPackage')
     */
    protected function assertCamelCaseNamespace(string $expected): void
    {
        $package = $this->getPackage();

        $this->assertEquals(
            $expected,
            $package->getCamelCaseNamespace(),
            'CamelCase namespace format mismatch'
        );
    }

    /**
     * Assert PascalCase namespace format
     *
     * @param string $expected Expected PascalCase format (e.g., 'VendorPackage')
     */
    protected function assertPascalCaseNamespace(string $expected): void
    {
        $package = $this->getPackage();

        $this->assertEquals(
            $expected,
            $package->getPascalCaseNamespace(),
            'PascalCase namespace format mismatch'
        );
    }

    /**
     * Assert config uses namespaced key
     *
     * @param string $configFileName Base config file name
     */
    protected function assertConfigUsesNamespacedKey(string $configFileName): void
    {
        $package = $this->getPackage();
        $namespacedKey = $package->getNamespacedConfigKey($configFileName);

        $this->assertTrue(
            config()->has($namespacedKey),
            "Config with namespaced key '{$namespacedKey}' is not registered"
        );

        // Ensure non-namespaced key is NOT registered (to prevent collisions)
        if ($package->hasConfigNamespacing()) {
            $this->assertFalse(
                config()->has($configFileName),
                "Config is registered with non-namespaced key '{$configFileName}' when namespacing is enabled"
            );
        }
    }

    /**
     * Assert publish tag uses namespaced format
     *
     * @param string $suffix Tag suffix (e.g., 'config', 'migrations')
     */
    protected function assertPublishTagUsesNamespace(string $suffix): void
    {
        $package = $this->getPackage();
        $expectedTag = $package->getNamespacedPublishTag($suffix);

        $this->assertPublishableGroupExists($expectedTag);
    }

    /**
     * Test all namespace format methods return expected values
     *
     * Comprehensive test for all namespace format methods.
     *
     * @param array $expectedFormats Associative array of format => expected value
     *
     * @example
     * $this->assertNamespaceFormats([
     *     'dotted' => 'ichava.tabler-icons',
     *     'dashed' => 'ichava-tabler-icons',
     *     'underscore' => 'ichava_tabler_icons',
     *     'camelCase' => 'ichavaTablerIcons',
     *     'pascalCase' => 'IchavaTablerIcons',
     * ]);
     */
    protected function assertNamespaceFormats(array $expectedFormats): void
    {
        $package = $this->getPackage();

        foreach ($expectedFormats as $format => $expected) {
            $method = 'get' . ucfirst((string) $format) . 'Namespace';

            if (! method_exists($package, $method)) {
                $this->fail("Method '{$method}' does not exist on Package class");
            }

            $actual = $package->$method();
            $this->assertEquals(
                $expected,
                $actual,
                "Namespace format '{$format}' mismatch. Expected '{$expected}', got '{$actual}'"
            );
        }
    }

    /**
     * Assert package uses config namespacing
     */
    protected function assertPackageUsesConfigNamespacing(): void
    {
        $package = $this->getPackage();

        $this->assertTrue(
            $package->hasConfigNamespacing(),
            'Package does not use config namespacing'
        );

        $this->assertNotNull(
            $package->getConfigVendor(),
            'Config vendor is null when namespacing should be enabled'
        );
    }

    /**
     * Assert package does not use config namespacing
     */
    protected function assertPackageDoesNotUseConfigNamespacing(): void
    {
        $package = $this->getPackage();

        $this->assertFalse(
            $package->hasConfigNamespacing(),
            'Package uses config namespacing when it should not'
        );

        $this->assertNull(
            $package->getConfigVendor(),
            'Config vendor is not null when namespacing should be disabled'
        );
    }
}
