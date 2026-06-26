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
}
