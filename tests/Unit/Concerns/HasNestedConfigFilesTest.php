<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Concerns;

use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Package\Tools\Package;

/**
 * Tests for the HasNestedConfigFiles builder concern: it registers
 * folder-namespaced config files onto Package::$namespacedConfigFiles,
 * keyed by their folder-derived dotted key.
 */
class HasNestedConfigFilesTest extends TestCase
{
    private Package $package;

    private string $fixtureRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureRoot = dirname(__DIR__, 2) . '/fixtures/nested-config-package';

        $this->package = new Package;
        $this->package
            ->setName('acme/widget')
            // Point the package root at the fixture tree before any nested
            // config registration; the resolver is built lazily from it.
            ->setPathFrom($this->fixtureRoot);
    }

    #[Test]
    public function it_registers_a_nested_config_with_folder_derived_key(): void
    {
        $result = $this->package->hasNestedConfig('panel', 'admin');

        $this->assertSame($this->package, $result, 'Should support fluent chaining');
        $this->assertCount(1, $this->package->namespacedConfigFiles);

        $entry = $this->package->namespacedConfigFiles[0];
        $this->assertSame('admin.panel', $entry['key']);
        $this->assertSame('admin/panel.php', $entry['relative']);
        $this->assertStringEndsWith('config/admin/panel.php', str_replace('\\', '/', $entry['path']));
    }

    #[Test]
    public function it_derives_dotted_keys_from_deep_folders(): void
    {
        $this->package->hasNestedConfig('limits', 'api/v1');

        $entry = $this->package->namespacedConfigFiles[0];
        $this->assertSame('api.v1.limits', $entry['key']);
        $this->assertSame('api/v1/limits.php', $entry['relative']);
    }

    #[Test]
    public function it_honours_an_explicit_key_override(): void
    {
        $this->package->hasNestedConfig('panel', 'admin', 'custom.override');

        $entry = $this->package->namespacedConfigFiles[0];
        $this->assertSame('custom.override', $entry['key']);
        // The publishable relative path still reflects the on-disk folder.
        $this->assertSame('admin/panel.php', $entry['relative']);
    }

    #[Test]
    public function it_skips_files_that_do_not_exist(): void
    {
        $this->package->hasNestedConfig('missing', 'admin');

        $this->assertSame([], $this->package->namespacedConfigFiles);
    }

    #[Test]
    public function it_registers_many_files_from_one_folder(): void
    {
        $this->package->hasNestedConfigs(['panel'], 'admin');

        $this->assertCount(1, $this->package->namespacedConfigFiles);
        $this->assertSame('admin.panel', $this->package->namespacedConfigFiles[0]['key']);
    }

    #[Test]
    public function discovers_config_recursively_registers_all_with_folder_keys(): void
    {
        $this->package->discoversConfig();

        $keys = array_column($this->package->namespacedConfigFiles, 'key');
        sort($keys);

        // Every .php under config/ is mounted at its folder-derived key,
        // including the flat top-level file.
        $this->assertSame(
            ['admin.panel', 'api.v1.limits', 'custom.thing', 'widget'],
            $keys
        );
    }

    #[Test]
    public function discovers_config_prefixes_keys_with_a_namespace(): void
    {
        $this->package->discoversConfig('acme');

        $keys = array_column($this->package->namespacedConfigFiles, 'key');
        sort($keys);

        $this->assertSame(
            ['acme.admin.panel', 'acme.api.v1.limits', 'acme.custom.thing', 'acme.widget'],
            $keys
        );
    }

    #[Test]
    public function discovers_config_scopes_to_a_subfolder(): void
    {
        $this->package->discoversConfig('', 'api');

        $keys = array_column($this->package->namespacedConfigFiles, 'key');

        $this->assertSame(['api.v1.limits'], $keys);
    }

    #[Test]
    public function it_deduplicates_nested_configs_by_source_path(): void
    {
        $this->package
            ->hasNestedConfig('panel', 'admin')
            ->hasNestedConfig('panel', 'admin')
            // Same file reached via discovery should not double-register.
            ->discoversConfig();

        $paths = array_column($this->package->namespacedConfigFiles, 'path');

        $this->assertSame($paths, array_unique($paths), 'Entries must be unique by path');
        $this->assertCount(4, $this->package->namespacedConfigFiles);
    }

    #[Test]
    public function has_config_file_does_not_touch_nested_config_list(): void
    {
        $this->package->hasConfigFile('widget');

        // Backward compatibility: flat registration only mutates the flat
        // configFileNames list, never namespacedConfigFiles.
        $this->assertContains('widget', $this->package->configFileNames);
        $this->assertSame([], $this->package->namespacedConfigFiles);
    }
}
