<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Concerns;

use Illuminate\Console\Command;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Services\Discovery\AttributeDiscoverer;
use Simtabi\Laranail\Package\Tools\Tests\Fixtures\AutoLoad\Commands\FixtureFooCommand;
use Simtabi\Laranail\Package\Tools\Tests\Fixtures\AutoLoad\Commands\FixtureNotACommand;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

/**
 * Covers HasBatchResourceLoading::autoLoadCommands() and the underlying
 * AttributeDiscoverer::discoverSubclasses() generator it reuses.
 */
final class AutoLoadCommandsTest extends TestCase
{
    private const string FIXTURE_NAMESPACE = 'Simtabi\\Laranail\\Package\\Tools\\Tests\\Fixtures\\AutoLoad';

    private function fixtureCommandsDir(): string
    {
        return __DIR__ . '/../../Fixtures/AutoLoad/Commands';
    }

    private function makePackage(): Package
    {
        // Anonymous Package subclass that exposes a root namespace (default
        // Package has no getNamespace()) and makes the protected loader
        // callable from the test.
        return new class extends Package
        {
            public function getNamespace(): string
            {
                return 'Simtabi\\Laranail\\Package\\Tools\\Tests\\Fixtures\\AutoLoad';
            }

            public function callAutoLoadCommands(?string $dir = null): static
            {
                return $this->autoLoadCommands($dir);
            }
        };
    }

    public function test_auto_load_commands_registers_only_command_subclasses(): void
    {
        $package = $this->makePackage();
        $package->setName('acme/fixture');

        /** @var Package&object{callAutoLoadCommands: callable} $package */
        $package->callAutoLoadCommands($this->fixtureCommandsDir());

        $this->assertContains(FixtureFooCommand::class, $package->commands);
        $this->assertNotContains(FixtureNotACommand::class, $package->commands);
    }

    public function test_auto_load_commands_is_noop_for_missing_directory(): void
    {
        $package = $this->makePackage();
        $package->setName('acme/fixture');

        /** @var Package&object{callAutoLoadCommands: callable} $package */
        $package->callAutoLoadCommands($this->fixtureCommandsDir() . '/does-not-exist');

        $this->assertSame([], $package->commands);
    }

    public function test_auto_load_commands_is_noop_without_resolvable_namespace(): void
    {
        // Plain Package has no getNamespace(): the loader must bail out.
        $package = new class extends Package
        {
            public function callAutoLoadCommands(?string $dir = null): static
            {
                return $this->autoLoadCommands($dir);
            }
        };
        $package->setName('acme/fixture');

        /** @var Package&object{callAutoLoadCommands: callable} $package */
        $package->callAutoLoadCommands($this->fixtureCommandsDir());

        $this->assertSame([], $package->commands);
    }

    public function test_discover_subclasses_yields_only_instantiable_command_subclasses(): void
    {
        $found = iterator_to_array(
            (new AttributeDiscoverer)->discoverSubclasses(
                $this->fixtureCommandsDir(),
                self::FIXTURE_NAMESPACE . '\\Commands',
                Command::class,
            )
        );

        $this->assertContains(FixtureFooCommand::class, $found);
        // Not a Command subclass.
        $this->assertNotContains(FixtureNotACommand::class, $found);
        // Abstract Command subclass is skipped (not instantiable).
        $this->assertNotContains(
            self::FIXTURE_NAMESPACE . '\\Commands\\FixtureAbstractCommand',
            $found,
        );
    }

    public function test_discover_subclasses_throws_for_missing_directory(): void
    {
        $this->expectException(RuntimeException::class);

        iterator_to_array(
            (new AttributeDiscoverer)->discoverSubclasses(
                $this->fixtureCommandsDir() . '/nope',
                self::FIXTURE_NAMESPACE . '\\Commands',
                Command::class,
            )
        );
    }
}
