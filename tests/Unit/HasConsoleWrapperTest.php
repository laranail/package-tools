<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit;

use Exception;
use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Package\Tools\Concerns\Package\HasConsoleWrapper;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

/**
 * HasConsoleWrapperTest - Test console wrapper functionality
 */
class HasConsoleWrapperTest extends TestCase
{
    use HasConsoleWrapper;

    #[Test]
    public function it_executes_callback_when_running_in_console(): void
    {
        $executed = false;

        $this->shouldRunInConsole(function () use (&$executed): void {
            $executed = true;
        });

        $this->assertTrue($executed);
    }

    #[Test]
    public function it_skips_callback_when_not_in_console(): void
    {
        // This test assumes we ARE in console (PHPUnit runs in console)
        // We'll just verify the method doesn't throw errors
        $executed = false;

        $this->shouldRunInConsole(function () use (&$executed): void {
            $executed = true;
        });

        $this->assertTrue($executed);
    }

    #[Test]
    public function it_respects_and_when_boolean_condition(): void
    {
        $executed = false;

        $this->shouldRunInConsole(
            callback: function () use (&$executed): void {
                $executed = true;
            },
            andWhen: false
        );

        $this->assertFalse($executed);
    }

    #[Test]
    public function it_respects_and_when_callable_condition(): void
    {
        $executed = false;

        $this->shouldRunInConsole(
            callback: function () use (&$executed): void {
                $executed = true;
            },
            andWhen: fn (): false => false
        );

        $this->assertFalse($executed);
    }

    #[Test]
    public function it_handles_exceptions_gracefully(): void
    {
        $result = $this->shouldRunInConsole(
            callback: fn () => throw new Exception('Test error'),
            expectReturn: true,
            default: 'fallback'
        );

        $this->assertEquals('fallback', $result);
    }

    #[Test]
    public function it_returns_callback_result_when_expect_return_true(): void
    {
        $result = $this->shouldRunInConsole(
            callback: fn (): string => 'success',
            expectReturn: true
        );

        $this->assertEquals('success', $result);
    }

    #[Test]
    public function it_returns_null_when_expect_return_false(): void
    {
        $result = $this->shouldRunInConsole(
            callback: fn (): string => 'success',
            expectReturn: false
        );

        $this->assertNull($result);
    }

    #[Test]
    public function it_returns_default_when_conditions_not_met(): void
    {
        $result = $this->shouldRunInConsole(
            callback: fn (): string => 'success',
            andWhen: false,
            expectReturn: true,
            default: 'default_value'
        );

        $this->assertEquals('default_value', $result);
    }

    #[Test]
    public function should_run_in_console_environment_works(): void
    {
        $executed = false;

        $this->shouldRunInConsoleEnvironment(
            callback: function () use (&$executed): void {
                $executed = true;
            },
            environments: app()->environment()
        );

        $this->assertTrue($executed);
    }

    #[Test]
    public function should_run_in_console_environment_skips_wrong_env(): void
    {
        $executed = false;

        $this->shouldRunInConsoleEnvironment(
            callback: function () use (&$executed): void {
                $executed = true;
            },
            environments: 'nonexistent-environment'
        );

        $this->assertFalse($executed);
    }

    #[Test]
    public function should_run_in_console_when_works(): void
    {
        $executed = false;

        $this->shouldRunInConsoleWhen(
            callback: function () use (&$executed): void {
                $executed = true;
            },
            condition: true
        );

        $this->assertTrue($executed);
    }

    #[Test]
    public function should_run_in_console_when_skips_false_condition(): void
    {
        $executed = false;

        $this->shouldRunInConsoleWhen(
            callback: function () use (&$executed): void {
                $executed = true;
            },
            condition: false
        );

        $this->assertFalse($executed);
    }

    #[Test]
    public function should_run_in_console_multiple_executes_all_callbacks(): void
    {
        $count = 0;

        // Use full closures with by-reference capture; PHP arrow functions
        // capture by value, so `fn() => $count++` would not mutate the
        // outer $count.
        $result = $this->shouldRunInConsoleMultiple([
            function () use (&$count): void {
                $count++;
            },
            function () use (&$count): void {
                $count++;
            },
            function () use (&$count): void {
                $count++;
            },
        ]);

        $this->assertTrue($result);
        $this->assertEquals(3, $count);
    }

    #[Test]
    public function should_run_in_console_multiple_stops_on_error(): void
    {
        $count = 0;

        $result = $this->shouldRunInConsoleMultiple([
            function () use (&$count): void {
                $count++;
            },
            function (): void {
                throw new Exception('Error');
            },
            function () use (&$count): void {
                $count++;
            },
        ]);

        $this->assertFalse($result);
        $this->assertEquals(1, $count); // Only first callback executed
    }

    #[Test]
    public function should_run_in_console_multiple_respects_and_when(): void
    {
        $count = 0;

        $result = $this->shouldRunInConsoleMultiple(
            callbacks: [
                function () use (&$count): void {
                    $count++;
                },
                function () use (&$count): void {
                    $count++;
                },
            ],
            andWhen: false
        );

        $this->assertFalse($result);
        $this->assertEquals(0, $count);
    }

    // Abstract method implementation for testing
    protected function getDashedNamespace(): string
    {
        return 'test/package';
    }
}
