<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Enums;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\Package\Tools\Enums\Weekday;

/**
 * the cron day-of-week contract: sunday = 0 through saturday = 6, exactly
 * seven cases.
 */
final class WeekdayEnumTest extends TestCase
{
    #[Test]
    public function it_has_exactly_seven_cases(): void
    {
        $this->assertCount(7, Weekday::cases());
    }

    #[Test]
    public function cases_follow_cron_numbering_sunday_zero_through_saturday_six(): void
    {
        $this->assertSame(0, Weekday::Sunday->value);
        $this->assertSame(1, Weekday::Monday->value);
        $this->assertSame(2, Weekday::Tuesday->value);
        $this->assertSame(3, Weekday::Wednesday->value);
        $this->assertSame(4, Weekday::Thursday->value);
        $this->assertSame(5, Weekday::Friday->value);
        $this->assertSame(6, Weekday::Saturday->value);
    }

    #[Test]
    public function values_resolve_back_through_from(): void
    {
        foreach (range(0, 6) as $value) {
            $this->assertSame($value, Weekday::from($value)->value);
        }
    }
}
