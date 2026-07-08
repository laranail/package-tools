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
    public function duplicate_explicit_seeders_resolve_once(): void
    {
        $definition = AutoSeederDefinition::make('acme/blog')
            ->seeders([AlphaFixtureSeeder::class, BetaFixtureSeeder::class, AlphaFixtureSeeder::class]);

        // a seeder listed twice must not run twice
        $this->assertSame(
            [AlphaFixtureSeeder::class, BetaFixtureSeeder::class],
            $definition->resolveSeeders('/irrelevant-default'),
        );
    }

    #[Test]
    public function discovery_over_a_missing_directory_resolves_to_nothing(): void
    {
        // a package that ships no seeders directory registers nothing —
        // boot must not throw
        $definition = AutoSeederDefinition::make('acme/blog');

        $this->assertSame([], $definition->resolveSeeders('/definitely/not/a/real/path'));
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
    public function autorun_is_off_by_default_and_autorun_now_is_an_alias(): void
    {
        $this->assertFalse(AutoSeederDefinition::make('a')->isAutorun());

        $primary = AutoSeederDefinition::make('a')->autorunAfterMigrations();
        $alias = AutoSeederDefinition::make('b')->autorunNow();

        $this->assertTrue($primary->isAutorun());
        $this->assertTrue($alias->isAutorun());
        $this->assertSame(
            $primary->toArray()['autorun'],
            $alias->toArray()['autorun'],
        );

        $this->assertFalse(AutoSeederDefinition::make('c')->autorunNow(false)->isAutorun());
    }

    #[Test]
    public function autorun_environments_accept_enums_and_strings(): void
    {
        $definition = AutoSeederDefinition::make('a')->autorunInEnvironments(
            \Simtabi\Laranail\Package\Tools\Enums\Environment::Local,
            'staging',
        );

        $this->assertSame(['local', 'staging'], $definition->autorunEnvironmentsValue());
    }

    #[Test]
    public function add_seeders_appends_in_order_and_dedupes(): void
    {
        $definition = AutoSeederDefinition::make('a')
            ->seeders([AlphaFixtureSeeder::class])
            ->addSeeders(BetaFixtureSeeder::class, AlphaFixtureSeeder::class)
            ->addSeeders(GammaFixtureSeeder::class);

        $this->assertSame(
            [AlphaFixtureSeeder::class, BetaFixtureSeeder::class, GammaFixtureSeeder::class],
            $definition->toArray()['seeders'],
        );
    }

    #[Test]
    public function stop_on_failure_flag_round_trips(): void
    {
        $this->assertFalse(AutoSeederDefinition::make('a')->shouldStopOnFailure());
        $this->assertTrue(AutoSeederDefinition::make('a')->stopOnFailure()->shouldStopOnFailure());
    }

    #[Test]
    public function explicit_priority_is_tracked_separately_from_the_default(): void
    {
        // The A9 fix: a never-called priority() must not clobber an
        // options(['priority' => …]) value at boot-merge time.
        $this->assertFalse(AutoSeederDefinition::make('a')->hasExplicitPriority());
        $this->assertSame(0, AutoSeederDefinition::make('a')->priorityValue());

        $explicit = AutoSeederDefinition::make('a')->priority(7);
        $this->assertTrue($explicit->hasExplicitPriority());
        $this->assertSame(7, $explicit->priorityValue());
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
            'autorun' => false,
            'autorun_environments' => [],
            'stop_on_failure' => false,
            'options' => ['fire_events' => true],
        ];

        $this->assertSame($expected, $definition->toArray());
        $this->assertSame(json_encode($expected), $definition->toJson());
        $this->assertSame($expected, $definition->jsonSerialize());
    }
}
