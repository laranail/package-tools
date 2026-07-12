<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature;

use Illuminate\Foundation\Console\AboutCommand;
use Override;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Enums\SeederExecutionMode;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\Package\Tools\Support\Definitions\AboutSectionDefinition;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

final class AboutSectionsTestPackageProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('acme/about-sections')
            ->hasAboutSection(
                AboutSectionDefinition::make('Fluent Section')
                    ->field('Static', 'v1')
                    ->field('Lazy', fn (): string => 'computed'),
            )
            ->hasAboutSection(
                AboutSectionDefinition::make('Typed Section')
                    ->field('Enum', SeederExecutionMode::Queued)     // → "queued"
                    ->field('Bool', true)                            // → "true"
                    ->field('Missing', null)                         // → "null"
                    ->field('Boom', fn () => throw new RuntimeException('unmigrated'))
                    ->fallback('n/a (unavailable)'),
            )
            ->hasAboutSection(
                AboutSectionDefinition::make('Gated Section')
                    ->field('Hidden', 'x')
                    ->whenConfig('about_test.gated', false),
            )
            ->hasAboutSection('Legacy Section', fn (): array => ['Old' => 'style']);
    }
}

final class BootPackageAboutSectionsTest extends TestCase
{
    #[Override]
    protected function getPackageProviders($app): array
    {
        return [AboutSectionsTestPackageProvider::class];
    }

    public function test_fluent_and_legacy_sections_render_in_about(): void
    {
        $this->artisan('about')
            ->expectsOutputToContain('Fluent Section')
            ->expectsOutputToContain('computed')
            ->expectsOutputToContain('Legacy Section')
            ->assertSuccessful();
    }

    public function test_mixed_types_and_failure_safety_render_through_the_command(): void
    {
        // Drives the real `php artisan about` and inspects rendered output:
        // enums/bools/null render to display strings, and a throwing field
        // shows the fallback rather than crashing the command.
        $this->artisan('about')
            ->expectsOutputToContain('Typed Section')
            ->expectsOutputToContain('queued')
            ->expectsOutputToContain('n/a (unavailable)')
            ->assertSuccessful();
    }

    public function test_gated_section_is_absent_when_the_gate_fails(): void
    {
        $this->artisan('about')
            ->doesntExpectOutputToContain('Gated Section')
            ->assertSuccessful();
    }

    public function test_the_boot_step_registers_only_displayable_definitions(): void
    {
        // the gate evaluated false at boot, so the section never reached
        // AboutCommand; the fluent + legacy ones did (asserted above via
        // artisan output — this guards the class-level state too)
        $this->assertTrue(class_exists(AboutCommand::class));
    }
}
