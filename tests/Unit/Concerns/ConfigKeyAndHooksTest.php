<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Concerns;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\Package\Tools\Package;

class ConfigKeyAndHooksTest extends TestCase
{
    #[Test]
    public function default_config_file_maps_to_the_bare_namespace_others_get_subkeys(): void
    {
        $package = (new Package)->name('acme/widget'); // vendor=acme, short=widget

        // The default file (== short name) maps to the bare dotted namespace…
        $this->assertSame('acme.widget', $package->getNamespacedConfigKey('widget'));

        // …additional files get a per-file sub-key (no collision).
        $this->assertSame('acme.widget.feature-toggles', $package->getNamespacedConfigKey('feature-toggles'));
        $this->assertSame('acme.widget.security', $package->getNamespacedConfigKey('security'));
    }

    #[Test]
    public function child_providers_are_recorded(): void
    {
        $package = (new Package)->name('acme/widget')
            ->hasChildProviders([Package::class, Package::class]);

        $this->assertSame([Package::class, Package::class], $package->childProviders);
    }

    #[Test]
    public function validation_rules_are_recorded(): void
    {
        $package = (new Package)->name('acme/widget')
            ->hasValidationRule('my_rule', Package::class, 'The :attribute failed.');

        $this->assertCount(1, $package->validationRules);
        $this->assertSame('my_rule', $package->validationRules[0]['name']);
        $this->assertSame(Package::class, $package->validationRules[0]['rule']);
        $this->assertSame('The :attribute failed.', $package->validationRules[0]['message']);
    }

    #[Test]
    public function about_sections_are_recorded(): void
    {
        $package = (new Package)->name('acme/widget')
            ->hasAboutSection('Acme', static fn (): array => ['Version' => '1.0']);

        $this->assertCount(1, $package->aboutSections);
        $this->assertSame('Acme', $package->aboutSections[0]['label']);
        $this->assertSame(['Version' => '1.0'], ($package->aboutSections[0]['data'])());
    }

    #[Test]
    public function has_validation_rules_batch_supports_class_and_class_message_forms(): void
    {
        $package = (new Package)->name('acme/widget')->hasValidationRules([
            'bare' => Package::class,
            'with_message' => [Package::class, 'The :attribute failed.'],
        ]);

        $this->assertCount(2, $package->validationRules);
        $this->assertSame('bare', $package->validationRules[0]['name']);
        $this->assertNull($package->validationRules[0]['message']);
        $this->assertSame('with_message', $package->validationRules[1]['name']);
        $this->assertSame('The :attribute failed.', $package->validationRules[1]['message']);
    }

    #[Test]
    public function has_about_sections_batch_is_keyed_by_label(): void
    {
        $package = (new Package)->name('acme/widget')->hasAboutSections([
            'One' => static fn (): array => ['a' => 1],
            'Two' => static fn (): array => ['b' => 2],
        ]);

        $this->assertCount(2, $package->aboutSections);
        $this->assertSame(['One', 'Two'], array_column($package->aboutSections, 'label'));
    }

    #[Test]
    public function has_doctor_checks_batch_fans_out_to_the_singular(): void
    {
        $package = (new Package)->name('acme/widget')->hasDoctorChecks([Package::class, Package::class]);

        $this->assertCount(2, $package->getDoctorChecks());
    }

    #[Test]
    public function shares_data_with_all_views_accepts_a_batch_array(): void
    {
        $package = (new Package)->name('acme/widget')
            ->sharesDataWithAllViews(['site' => 'Acme', 'year' => 2026]);

        $this->assertSame(['site' => 'Acme', 'year' => 2026], $package->sharedViewData);
    }

    #[Test]
    public function publish_file_registers_a_namespaced_tag(): void
    {
        $package = (new Package)->name('acme/widget')
            ->publishFile('/abs/config/security.php', '/dest/acme-security.php');

        $this->assertSame(
            ['/abs/config/security.php' => '/dest/acme-security.php'],
            $package->getPublishPaths()['acme::widget-security'] ?? null,
        );
    }

    #[Test]
    public function publish_directory_registers_a_namespaced_tag(): void
    {
        $package = (new Package)->name('acme/widget')
            ->publishDirectory('/abs/stubs', '/dest/stubs');

        $this->assertSame(
            ['/abs/stubs' => '/dest/stubs'],
            $package->getPublishPaths()['acme::widget-stubs'] ?? null,
        );
    }

    #[Test]
    public function publish_file_derives_the_suffix_from_the_filename_when_omitted(): void
    {
        $package = (new Package)->name('acme/widget')
            ->publishFile('/abs/config/security.php', '/dest/acme-security.php');

        // Suffix defaults to the source filename without extension → tag `…-security`.
        $this->assertArrayHasKey('acme::widget-security', $package->getPublishPaths());
    }

    #[Test]
    public function publish_file_and_directory_register_cleanup_when_clean_is_true(): void
    {
        $package = (new Package)->name('acme/widget')
            ->publishFile('/abs/config/security.php', '/dest/acme-security.php', null, true)
            ->publishDirectory('/abs/stubs', '/dest/stubs', null, true);

        $toClean = $package->getPublishPathsToClean();
        $this->assertArrayHasKey('acme::widget-security', $toClean);
        $this->assertArrayHasKey('acme::widget-stubs', $toClean);
    }

    #[Test]
    public function register_namespaced_configs_batch_registers_each_entry(): void
    {
        $package = (new Package)->name('acme/widget')->registerNamespacedConfigs([
            ['path' => '/abs/admin/panel.php', 'key' => 'admin.panel', 'relative' => 'admin/panel.php'],
            ['path' => '/abs/admin/settings.php', 'key' => 'admin.settings', 'relative' => 'admin/settings.php'],
        ]);

        $this->assertCount(2, $package->namespacedConfigFiles);
        $this->assertSame(['admin.panel', 'admin.settings'], array_column($package->namespacedConfigFiles, 'key'));
    }
}
