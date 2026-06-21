<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Integration;

use Orchestra\Testbench\TestCase;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Support\ForeignKeyCheckGuard;

final class ForeignKeyCheckGuardTest extends TestCase
{
    public function test_run_returns_callback_value(): void
    {
        $guard = new ForeignKeyCheckGuard;

        $value = $guard->run(static fn (): int => 42);

        $this->assertSame(42, $value);
        $this->assertSame(0, $guard->depth());
    }

    public function test_nested_calls_only_toggle_schema_once(): void
    {
        $guard = new ForeignKeyCheckGuard;
        $observed = [];

        $guard->run(function () use ($guard, &$observed): void {
            $observed[] = $guard->depth();
            $guard->run(function () use ($guard, &$observed): void {
                $observed[] = $guard->depth();
            });
            $observed[] = $guard->depth();
        });

        $this->assertSame([1, 2, 1], $observed);
        $this->assertSame(0, $guard->depth());
    }

    public function test_callback_exception_still_restores_depth(): void
    {
        $guard = new ForeignKeyCheckGuard;

        try {
            $guard->run(static function (): never {
                throw new RuntimeException('seeder blew up');
            });
            $this->fail('expected exception was not thrown');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(0, $guard->depth());
    }
}
