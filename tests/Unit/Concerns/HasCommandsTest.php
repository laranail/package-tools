<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Concerns;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\Package\Tools\Package;

/**
 * Tests for HasCommands concern
 */
class HasCommandsTest extends TestCase
{
    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();
        $this->package = new Package;
        $this->package->setName('test-vendor/test-package');
    }

    #[Test]
    public function it_can_register_commands(): void
    {
        $commands = ['TestCommand', 'AnotherCommand'];

        $result = $this->package->hasCommands($commands);

        $this->assertSame($this->package, $result, 'Should support fluent chaining');
        $this->assertSame($commands, $this->package->commands);
    }

    #[Test]
    public function it_can_register_single_command(): void
    {
        $this->package->hasCommands(['SingleCommand']);

        $this->assertCount(1, $this->package->commands);
        $this->assertContains('SingleCommand', $this->package->commands);
    }

    #[Test]
    public function it_can_register_multiple_commands(): void
    {
        $commands = [
            'Command1',
            'Command2',
            'Command3',
        ];

        $this->package->hasCommands($commands);

        $this->assertCount(3, $this->package->commands);
        $this->assertSame($commands, $this->package->commands);
    }

    #[Test]
    public function it_stores_commands_as_array(): void
    {
        $this->package->hasCommands(['TestCommand']);

        $this->assertIsArray($this->package->commands);
    }

    #[Test]
    public function it_can_register_empty_commands_array(): void
    {
        $this->package->hasCommands([]);

        $this->assertIsArray($this->package->commands);
        $this->assertEmpty($this->package->commands);
    }

    #[Test]
    public function it_accepts_fully_qualified_class_names(): void
    {
        $commands = [
            'App\\Console\\Commands\\TestCommand',
            'App\\Console\\Commands\\AnotherCommand',
        ];

        $this->package->hasCommands($commands);

        $this->assertSame($commands, $this->package->commands);
    }
}
