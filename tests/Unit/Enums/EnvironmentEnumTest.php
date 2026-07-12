<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Enums;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\Package\Tools\Enums\Environment;

/**
 * the standard laravel environment names, and nothing else — custom
 * environments pass raw strings instead of growing this enum.
 */
final class EnvironmentEnumTest extends TestCase
{
    #[Test]
    public function it_has_exactly_four_cases(): void
    {
        $this->assertCount(4, Environment::cases());
    }

    #[Test]
    public function cases_carry_the_standard_laravel_environment_names(): void
    {
        $this->assertSame('production', Environment::Production->value);
        $this->assertSame('staging', Environment::Staging->value);
        $this->assertSame('local', Environment::Local->value);
        $this->assertSame('testing', Environment::Testing->value);
    }

    #[Test]
    public function values_resolve_back_through_try_from(): void
    {
        foreach (Environment::cases() as $case) {
            $this->assertSame($case, Environment::tryFrom($case->value));
        }

        $this->assertNull(Environment::tryFrom('custom-env'));
    }
}
