<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Package\Tools\Support\Definitions\AutoSeederDefinition;
use Simtabi\Laranail\Package\Tools\Tests\Fixtures\Seeders\AlphaFixtureSeeder;
use Simtabi\Laranail\Package\Tools\Tests\Fixtures\Seeders\BetaFixtureSeeder;
use Simtabi\Laranail\Package\Tools\Tests\Fixtures\Seeders\GammaFixtureSeeder;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

/**
 * the auto-seeder definition: explicit ordered lists vs directory
 * discovery, the shared ignore list, config gating, and serialization.
 */
final class AutoSeederDefinitionTest extends TestCase
{
    private string $fixtureSeedersPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureSeedersPath = dirname(__DIR__, 2) . '/fixtures/Seeders';
    }

    #[Test]
    public function explicit_seeders_keep_their_array_order(): void
    {
        $definition = AutoSeederDefinition::make('acme/blog')
            ->seeders([GammaFixtureSeeder::class, AlphaFixtureSeeder::class]);

        $this->assertSame(
            [GammaFixtureSeeder::class, AlphaFixtureSeeder::class],
            $definition->resolveSeeders('/irrelevant-default'),
        );
    }

    #[Test]
    public function ignore_seeders_excludes_from_the_explicit_list(): void
    {
        $definition = AutoSeederDefinition::make('acme/blog')
            ->seeders([AlphaFixtureSeeder::class, BetaFixtureSeeder::class, GammaFixtureSeeder::class])
            ->ignoreSeeders([BetaFixtureSeeder::class]);

        $this->assertSame(
            [AlphaFixtureSeeder::class, GammaFixtureSeeder::class],
            $definition->resolveSeeders('/irrelevant-default'),
        );
    }

    #[Test]
    public function discovery_mode_finds_seeders_in_the_default_path(): void
    {
        $definition = AutoSeederDefinition::make('acme/blog');

        $this->assertSame(
            [AlphaFixtureSeeder::class, BetaFixtureSeeder::class, GammaFixtureSeeder::class],
            $definition->resolveSeeders($this->fixtureSeedersPath),
        );
    }

    #[Test]
    public function discover_in_overrides_the_default_discovery_path(): void
    {
        $definition = AutoSeederDefinition::make('acme/blog')->discoverIn($this->fixtureSeedersPath);

        $this->assertSame(
            [AlphaFixtureSeeder::class, BetaFixtureSeeder::class, GammaFixtureSeeder::class],
            $definition->resolveSeeders(sys_get_temp_dir()),
        );
    }

    #[Test]
    public function ignore_applies_to_discovered_seeders_too(): void
    {
        $definition = AutoSeederDefinition::make('acme/blog')
            ->ignoreSeeders([AlphaFixtureSeeder::class, GammaFixtureSeeder::class]);

        $this->assertSame(
            [BetaFixtureSeeder::class],
            $definition->resolveSeeders($this->fixtureSeedersPath),
        );
    }

    #[Test]
    public function an_empty_result_after_exclusion_is_not_an_error(): void
    {
        $definition = AutoSeederDefinition::make('acme/blog')
            ->seeders([AlphaFixtureSeeder::class])
            ->ignoreSeeders([AlphaFixtureSeeder::class]);

        $this->assertSame([], $definition->resolveSeeders('/irrelevant-default'));
    }

    #[Test]
    public function getters_expose_key_namespace_priority_and_options(): void
    {
        $definition = AutoSeederDefinition::make('acme/blog')
            ->inNamespace('Acme\\Blog')
            ->priority(5)
            ->options(['fire_events' => true]);

        $this->assertSame('acme/blog', $definition->key());
        $this->assertSame('Acme\\Blog', $definition->namespace());
        $this->assertSame(5, $definition->priorityValue());
        $this->assertSame(['fire_events' => true], $definition->optionsValue());
    }

    #[Test]
    public function defaults_are_priority_zero_no_namespace_no_options(): void
    {
        $definition = AutoSeederDefinition::make('acme/blog');

        $this->assertNull($definition->namespace());
        $this->assertSame(0, $definition->priorityValue());
        $this->assertSame([], $definition->optionsValue());
    }

    #[Test]
    public function should_register_defaults_to_true_without_a_gate(): void
    {
        $this->assertTrue(AutoSeederDefinition::make('acme/blog')->shouldRegister());
    }

    #[Test]
    public function should_register_honours_a_truthy_gate(): void
    {
        config()->set('acme.seed', false);
        $this->assertFalse(
            AutoSeederDefinition::make('acme/blog')->whenConfig('acme.seed')->shouldRegister(),
        );

        config()->set('acme.seed', true);
        $this->assertTrue(
            AutoSeederDefinition::make('acme/blog')->whenConfig('acme.seed')->shouldRegister(),
        );
    }

    #[Test]
    public function should_register_honours_a_not_null_gate(): void
    {
        config()->set('acme.seed', false);
        $this->assertTrue(
            AutoSeederDefinition::make('acme/blog')->whenConfigNotNull('acme.seed')->shouldRegister(),
        );

        $this->assertFalse(
            AutoSeederDefinition::make('acme/blog')->whenConfigNotNull('acme.absent')->shouldRegister(),
        );
    }

    #[Test]
    public function it_serializes_to_array_and_json(): void
    {
        $definition = AutoSeederDefinition::make('acme/blog')
            ->seeders([AlphaFixtureSeeder::class])
            ->ignoreSeeders([BetaFixtureSeeder::class])
            ->discoverIn('/pkg/database/seeders')
            ->inNamespace('Acme\\Blog')
            ->whenConfig('acme.seed')
            ->priority(3)
            ->options(['fire_events' => true]);

        $expected = [
            'key' => 'acme/blog',
            'seeders' => [AlphaFixtureSeeder::class],
            'ignored' => [BetaFixtureSeeder::class],
            'discovery_path' => '/pkg/database/seeders',
            'namespace' => 'Acme\\Blog',
            'gate' => ['key' => 'acme.seed', 'default' => true, 'mode' => 'truthy'],
            'priority' => 3,
            'options' => ['fire_events' => true],
        ];

        $this->assertSame($expected, $definition->toArray());
        $this->assertSame(json_encode($expected), $definition->toJson());
        $this->assertSame($expected, $definition->jsonSerialize());
    }
}
