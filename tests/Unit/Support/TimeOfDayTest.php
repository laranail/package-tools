<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Support;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\Package\Tools\Support\Scheduling\TimeOfDay;

/**
 * the fluent time-of-day value: constructors and bounds, both parse
 * notations, wrapping arithmetic, and the canonical output formats.
 */
final class TimeOfDayTest extends TestCase
{
    #[Test]
    public function at_accepts_the_full_24_hour_range(): void
    {
        $this->assertSame('00:00', TimeOfDay::at(0)->format24());
        $this->assertSame('23:59', TimeOfDay::at(23, 59)->format24());
        $this->assertSame(17, TimeOfDay::at(17, 30)->hour());
        $this->assertSame(30, TimeOfDay::at(17, 30)->minute());
    }

    #[Test]
    public function at_rejects_an_hour_above_23(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('hour must be 0-23');

        TimeOfDay::at(24);
    }

    #[Test]
    public function at_rejects_a_negative_hour(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TimeOfDay::at(-1);
    }

    #[Test]
    public function at_rejects_a_minute_above_59(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('minute must be 0-59');

        TimeOfDay::at(12, 60);
    }

    #[Test]
    public function at_rejects_a_negative_minute(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TimeOfDay::at(12, -1);
    }

    #[Test]
    public function parse_reads_24_hour_notation(): void
    {
        $this->assertSame('17:30', TimeOfDay::parse('17:30')->format24());
        $this->assertSame('05:00', TimeOfDay::parse('05:00')->format24());
    }

    #[Test]
    public function parse_reads_12_hour_notation(): void
    {
        $this->assertSame('17:30', TimeOfDay::parse('5:30pm')->format24());
        $this->assertSame('17:00', TimeOfDay::parse('5 PM')->format24());
        $this->assertSame('05:30', TimeOfDay::parse('5:30 AM')->format24());
    }

    #[Test]
    public function parse_maps_the_midnight_and_noon_edge_cases(): void
    {
        $this->assertSame('00:00', TimeOfDay::parse('12am')->format24());
        $this->assertSame('12:00', TimeOfDay::parse('12pm')->format24());
    }

    #[Test]
    public function parse_rejects_garbage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('unparseable time');

        TimeOfDay::parse('half past nine');
    }

    #[Test]
    public function parse_rejects_a_13_on_the_12_hour_clock(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('12-hour clock hour must be 1-12');

        TimeOfDay::parse('13pm');
    }

    #[Test]
    public function am_and_pm_constructors_map_to_24_hour_values(): void
    {
        $this->assertSame('09:15', TimeOfDay::am(9, 15)->format24());
        $this->assertSame('21:15', TimeOfDay::pm(9, 15)->format24());
        $this->assertSame('00:00', TimeOfDay::am(12)->format24());
        $this->assertSame('12:00', TimeOfDay::pm(12)->format24());
    }

    #[Test]
    public function am_rejects_hours_outside_1_to_12(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TimeOfDay::am(0);
    }

    #[Test]
    public function pm_rejects_hours_outside_1_to_12(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TimeOfDay::pm(13);
    }

    #[Test]
    public function plus_minutes_wraps_across_midnight(): void
    {
        $this->assertSame('00:00', TimeOfDay::at(23, 59)->plusMinutes(1)->format24());
        $this->assertSame('00:30', TimeOfDay::at(23, 45)->plusMinutes(45)->format24());
    }

    #[Test]
    public function minus_minutes_wraps_backwards_across_midnight(): void
    {
        $this->assertSame('23:59', TimeOfDay::at(0, 0)->minusMinutes(1)->format24());
        $this->assertSame('23:30', TimeOfDay::at(0, 15)->minusMinutes(45)->format24());
    }

    #[Test]
    public function plus_hours_adds_whole_hours_and_wraps(): void
    {
        $this->assertSame('14:30', TimeOfDay::at(12, 30)->plusHours(2)->format24());
        $this->assertSame('01:00', TimeOfDay::at(23)->plusHours(2)->format24());
    }

    #[Test]
    public function arithmetic_returns_new_instances(): void
    {
        $original = TimeOfDay::at(12, 0);
        $shifted = $original->plusMinutes(30);

        $this->assertNotSame($original, $shifted);
        $this->assertSame('12:00', $original->format24());
    }

    #[Test]
    public function format24_zero_pads_both_fields(): void
    {
        $this->assertSame('05:05', TimeOfDay::at(5, 5)->format24());
        $this->assertSame('00:00', TimeOfDay::at(0)->format24());
    }

    #[Test]
    public function format12_renders_meridiem_notation(): void
    {
        $this->assertSame('5:30 PM', TimeOfDay::parse('17:30')->format12());
        $this->assertSame('12:05 AM', TimeOfDay::parse('00:05')->format12());
        $this->assertSame('12:00 PM', TimeOfDay::at(12)->format12());
    }

    #[Test]
    public function it_serializes_to_array_and_json(): void
    {
        $time = TimeOfDay::at(17, 30);
        $expected = ['hour' => 17, 'minute' => 30, 'formatted' => '17:30'];

        $this->assertSame($expected, $time->toArray());
        $this->assertSame($expected, $time->jsonSerialize());
        $this->assertSame(json_encode($expected), $time->toJson());
    }
}
