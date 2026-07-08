<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Services\Database;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederBundle;

/**
 * the typed per-package seeder bundle: defaults, fluent setters, the
 * legacy fromOptions() bridge, and serialization.
 */
final class SeederBundleTest extends TestCase
{
    #[Test]
    public function make_stores_key_and_seeders_in_order(): void
    {
        $bundle = SeederBundle::make('acme/blog', ['B\\Seeder', 'A\\Seeder']);

        $this->assertSame('acme/blog', $bundle->key());
        $this->assertSame(['B\\Seeder', 'A\\Seeder'], $bundle->seeders());
    }

    #[Test]
    public function make_reindexes_the_seeder_list(): void
    {
        $bundle = SeederBundle::make('acme/blog', [3 => 'A\\Seeder', 7 => 'B\\Seeder']);

        $this->assertSame(['A\\Seeder', 'B\\Seeder'], $bundle->seeders());
    }

    #[Test]
    public function defaults_are_fk_checks_disabled_no_events_no_parameters_priority_zero(): void
    {
        $bundle = SeederBundle::make('acme/blog', []);

        $this->assertNull($bundle->namespace());
        $this->assertSame(0, $bundle->priorityValue());
        $this->assertTrue($bundle->disablesForeignKeyChecks());
        $this->assertFalse($bundle->shouldFireEvents());
        $this->assertSame([], $bundle->parametersValue());
    }

    #[Test]
    public function fluent_setters_configure_every_field(): void
    {
        $bundle = SeederBundle::make('acme/blog', ['A\\Seeder'])
            ->inNamespace('Acme\\Blog')
            ->priority(7)
            ->withoutForeignKeyChecks(false)
            ->firesEvents()
            ->parameters(['tenant' => 'acme']);

        $this->assertSame('Acme\\Blog', $bundle->namespace());
        $this->assertSame(7, $bundle->priorityValue());
        $this->assertFalse($bundle->disablesForeignKeyChecks());
        $this->assertTrue($bundle->shouldFireEvents());
        $this->assertSame(['tenant' => 'acme'], $bundle->parametersValue());
    }

    #[Test]
    public function from_options_maps_the_legacy_string_keyed_shape(): void
    {
        $bundle = SeederBundle::fromOptions('acme/blog', ['A\\Seeder'], 'Acme\\Blog', [
            'disable_foreign_key_checks' => false,
            'fire_events' => true,
            'parameters' => ['tenant' => 'acme'],
            'priority' => 3,
        ]);

        $this->assertSame('Acme\\Blog', $bundle->namespace());
        $this->assertFalse($bundle->disablesForeignKeyChecks());
        $this->assertTrue($bundle->shouldFireEvents());
        $this->assertSame(['tenant' => 'acme'], $bundle->parametersValue());
        $this->assertSame(3, $bundle->priorityValue());
    }

    #[Test]
    public function from_options_falls_back_to_the_defaults(): void
    {
        $bundle = SeederBundle::fromOptions('acme/blog', ['A\\Seeder'], null, []);

        $this->assertTrue($bundle->disablesForeignKeyChecks());
        $this->assertFalse($bundle->shouldFireEvents());
        $this->assertSame([], $bundle->parametersValue());
        $this->assertSame(0, $bundle->priorityValue());
    }

    #[Test]
    public function from_options_ignores_a_non_array_parameters_value(): void
    {
        $bundle = SeederBundle::fromOptions('acme/blog', [], null, ['parameters' => 'oops']);

        $this->assertSame([], $bundle->parametersValue());
    }

    #[Test]
    public function it_serializes_to_array(): void
    {
        $bundle = SeederBundle::make('acme/blog', ['A\\Seeder'])
            ->inNamespace('Acme\\Blog')
            ->priority(2)
            ->firesEvents()
            ->parameters(['tenant' => 'acme']);

        $this->assertSame([
            'key' => 'acme/blog',
            'seeders' => ['A\\Seeder'],
            'namespace' => 'Acme\\Blog',
            'priority' => 2,
            'disable_foreign_key_checks' => true,
            'fire_events' => true,
            'parameters' => ['tenant' => 'acme'],
            'autorun' => false,
            'stop_on_failure' => false,
            'autorun_environments' => [],
        ], $bundle->toArray());
    }
}
