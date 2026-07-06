<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Support;

use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Support\Definitions\InstallCommandDefinition;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

final class InstallCommandDefinitionTest extends TestCase
{
    public function test_steps_keep_declaration_order_with_builtins_interleaved(): void
    {
        $definition = InstallCommandDefinition::make()
            ->publishes('config')
            ->step('custom between', fn (): bool => true)
            ->runsMigrations()
            ->step('custom after', fn (): bool => true);

        $labels = array_map(static fn (array $step): string => $step['label'], $definition->steps());

        $this->assertSame(
            ['publish config', 'custom between', 'run migrations', 'custom after'],
            $labels,
        );
    }

    public function test_defaults_hidden_with_derived_signature(): void
    {
        $definition = InstallCommandDefinition::make();

        $this->assertTrue($definition->isHidden());
        $this->assertNull($definition->signature());
        $this->assertSame('acme:install', $definition->signature('acme:install'));
    }

    public function test_named_and_visible_override_the_defaults(): void
    {
        $definition = InstallCommandDefinition::make()
            ->named('acme:setup')
            ->visible();

        $this->assertFalse($definition->isHidden());
        $this->assertSame('acme:setup', $definition->signature('ignored:install'));
    }

    public function test_serialization_lists_step_labels(): void
    {
        $definition = InstallCommandDefinition::make()
            ->publishes('config', 'migrations')
            ->asksToRunMigrations()
            ->copiesServiceProvider()
            ->asksToStarRepo('laranail/package-tools');

        $array = $definition->toArray();

        $this->assertSame(
            ['publish config', 'publish migrations', 'ask to run migrations', 'copy service provider', 'ask to star repo'],
            $array['steps'],
        );
        $this->assertTrue($array['hidden']);
        $this->assertJson($definition->toJson());
    }

    public function test_package_stores_definitions_without_constructing_commands(): void
    {
        $package = new Package;
        $package->setName('acme/demo');

        $package->hasInstallCommand(InstallCommandDefinition::make()->publishes('config'));

        $this->assertCount(1, $package->getInstallCommandDefinitions());
        $this->assertSame([], $package->consoleCommands); // nothing constructed at configure time
    }
}
