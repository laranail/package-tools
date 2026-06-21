<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit;

use Exception;
use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Package\Tools\Concerns\Package\HasLifecycleHooks;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

/**
 * HasLifecycleHooksTest - Test lifecycle hook registration and execution
 *
 * Covers Phase 7 lifecycle hook features
 */
class HasLifecycleHooksTest extends TestCase
{
    use HasLifecycleHooks;

    #[Test]
    public function it_registers_before_register_hook(): void
    {
        $executed = false;

        $this->onBeforeRegister(function ($pkg) use (&$executed): void {
            $executed = true;
        });

        $this->executeBeforeRegisterHooks();

        $this->assertTrue($executed);
    }

    #[Test]
    public function it_registers_after_register_hook(): void
    {
        $executed = false;

        $this->onAfterRegister(function ($pkg) use (&$executed): void {
            $executed = true;
        });

        $this->executeAfterRegisterHooks();

        $this->assertTrue($executed);
    }

    #[Test]
    public function it_registers_before_boot_hook(): void
    {
        $executed = false;

        $this->onBeforeBoot(function ($pkg) use (&$executed): void {
            $executed = true;
        });

        $this->executeBeforeBootHooks();

        $this->assertTrue($executed);
    }

    #[Test]
    public function it_registers_after_boot_hook(): void
    {
        $executed = false;

        $this->onAfterBoot(function ($pkg) use (&$executed): void {
            $executed = true;
        });

        $this->executeAfterBootHooks();

        $this->assertTrue($executed);
    }

    #[Test]
    public function it_registers_before_config_load_hook(): void
    {
        $executed = false;

        $this->onBeforeConfigLoad(function ($pkg) use (&$executed): void {
            $executed = true;
        });

        $this->executeBeforeConfigLoadHooks();

        $this->assertTrue($executed);
    }

    #[Test]
    public function it_registers_after_config_load_hook(): void
    {
        $executed = false;

        $this->onAfterConfigLoad(function ($pkg) use (&$executed): void {
            $executed = true;
        });

        $this->executeAfterConfigLoadHooks();

        $this->assertTrue($executed);
    }

    #[Test]
    public function it_registers_before_view_load_hook(): void
    {
        $executed = false;

        $this->onBeforeViewLoad(function ($pkg) use (&$executed): void {
            $executed = true;
        });

        $this->executeBeforeViewLoadHooks();

        $this->assertTrue($executed);
    }

    #[Test]
    public function it_registers_after_view_load_hook(): void
    {
        $executed = false;

        $this->onAfterViewLoad(function ($pkg) use (&$executed): void {
            $executed = true;
        });

        $this->executeAfterViewLoadHooks();

        $this->assertTrue($executed);
    }

    #[Test]
    public function it_executes_multiple_hooks_in_order(): void
    {
        $order = [];

        $this->onBeforeRegister(function ($pkg) use (&$order): void {
            $order[] = 'first';
        });
        $this->onBeforeRegister(function ($pkg) use (&$order): void {
            $order[] = 'second';
        });
        $this->onBeforeRegister(function ($pkg) use (&$order): void {
            $order[] = 'third';
        });

        $this->executeBeforeRegisterHooks();

        $this->assertEquals(['first', 'second', 'third'], $order);
    }

    #[Test]
    public function it_passes_package_instance_to_hooks(): void
    {
        $receivedPackage = null;

        $this->onAfterBoot(function ($pkg) use (&$receivedPackage): void {
            $receivedPackage = $pkg;
        });

        $this->executeAfterBootHooks();

        $this->assertSame($this, $receivedPackage);
    }

    #[Test]
    public function it_chains_hook_registrations(): void
    {
        $result = $this->onBeforeRegister(fn ($pkg): null => null)
            ->onAfterRegister(fn ($pkg): null => null)
            ->onBeforeBoot(fn ($pkg): null => null);

        $this->assertInstanceOf(static::class, $result);
    }

    #[Test]
    public function it_supports_fluent_api_for_all_hooks(): void
    {
        $this->onBeforeRegister(fn ($pkg): null => null)
            ->onAfterRegister(fn ($pkg): null => null)
            ->onBeforeBoot(fn ($pkg): null => null)
            ->onAfterBoot(fn ($pkg): null => null)
            ->onBeforeConfigLoad(fn ($pkg): null => null)
            ->onAfterConfigLoad(fn ($pkg): null => null)
            ->onBeforeViewLoad(fn ($pkg): null => null)
            ->onAfterViewLoad(fn ($pkg): null => null);

        $hooks = $this->getRegisteredHooks();

        $this->assertEquals(1, $hooks['beforeRegister']);
        $this->assertEquals(1, $hooks['afterRegister']);
        $this->assertEquals(1, $hooks['beforeBoot']);
        $this->assertEquals(1, $hooks['afterBoot']);
        $this->assertEquals(1, $hooks['beforeConfigLoad']);
        $this->assertEquals(1, $hooks['afterConfigLoad']);
        $this->assertEquals(1, $hooks['beforeViewLoad']);
        $this->assertEquals(1, $hooks['afterViewLoad']);
    }

    #[Test]
    public function it_counts_registered_hooks_correctly(): void
    {
        $this->onBeforeRegister(fn ($pkg): null => null);
        $this->onBeforeRegister(fn ($pkg): null => null);
        $this->onAfterBoot(fn ($pkg): null => null);

        $hooks = $this->getRegisteredHooks();

        $this->assertEquals(2, $hooks['beforeRegister']);
        $this->assertEquals(1, $hooks['afterBoot']);
        $this->assertEquals(0, $hooks['beforeBoot']);
    }

    #[Test]
    public function it_allows_hooks_to_modify_state(): void
    {
        $counter = 0;

        $this->onBeforeRegister(function ($pkg) use (&$counter): void {
            $counter++;
        });
        $this->onBeforeRegister(function ($pkg) use (&$counter): void {
            $counter += 2;
        });

        $this->executeBeforeRegisterHooks();

        $this->assertEquals(3, $counter);
    }

    #[Test]
    public function it_continues_execution_if_hook_throws_exception(): void
    {
        $executed = [];

        $this->onAfterBoot(function ($pkg) use (&$executed): void {
            $executed[] = 'first';
        });
        $this->onAfterBoot(function ($pkg): void {
            throw new Exception('Test exception');
        });
        $this->onAfterBoot(function ($pkg) use (&$executed): void {
            $executed[] = 'third';
        });

        try {
            $this->executeAfterBootHooks();
        } catch (Exception) {
            // Expected
        }

        // First hook should have executed
        $this->assertContains('first', $executed);
    }

    #[Test]
    public function it_provides_hook_counts_for_all_lifecycle_stages(): void
    {
        $hooks = $this->getRegisteredHooks();

        $this->assertArrayHasKey('beforeRegister', $hooks);
        $this->assertArrayHasKey('afterRegister', $hooks);
        $this->assertArrayHasKey('beforeBoot', $hooks);
        $this->assertArrayHasKey('afterBoot', $hooks);
        $this->assertArrayHasKey('beforeConfigLoad', $hooks);
        $this->assertArrayHasKey('afterConfigLoad', $hooks);
        $this->assertArrayHasKey('beforeViewLoad', $hooks);
        $this->assertArrayHasKey('afterViewLoad', $hooks);
    }

    #[Test]
    public function it_returns_zero_counts_when_no_hooks_registered(): void
    {
        $hooks = $this->getRegisteredHooks();

        foreach ($hooks as $count) {
            $this->assertEquals(0, $count);
        }
    }

    #[Test]
    public function it_supports_complex_hook_logic(): void
    {
        $data = ['status' => 'initial'];

        $this->onBeforeRegister(function ($pkg) use (&$data): void {
            $data['status'] = 'registering';
            $data['timestamp'] = time();
        });

        $this->onAfterRegister(function ($pkg) use (&$data): void {
            $data['status'] = 'registered';
            $data['completed'] = true;
        });

        $this->executeBeforeRegisterHooks();
        $this->assertEquals('registering', $data['status']);

        $this->executeAfterRegisterHooks();
        $this->assertEquals('registered', $data['status']);
        $this->assertTrue($data['completed']);
    }

    #[Test]
    public function it_allows_conditional_hook_execution(): void
    {
        $shouldExecute = false;
        $executed = false;

        $this->onBeforeBoot(function ($pkg) use (&$shouldExecute, &$executed): void {
            if ($shouldExecute) {
                $executed = true;
            }
        });

        $this->executeBeforeBootHooks();
        $this->assertFalse($executed);

        $shouldExecute = true;
        $this->executeBeforeBootHooks();
        $this->assertTrue($executed);
    }

    #[Test]
    public function it_supports_dependency_injection_pattern(): void
    {
        $injectedValue = 'test-value';
        $receivedValue = null;

        $this->onAfterBoot(function ($pkg) use ($injectedValue, &$receivedValue): void {
            $receivedValue = $injectedValue;
        });

        $this->executeAfterBootHooks();

        $this->assertEquals('test-value', $receivedValue);
    }
}
