<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Enums;

use Illuminate\Console\Scheduling\Event;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\Package\Tools\Enums\Cadence;

/**
 * guards enum drift from laravel: every Cadence value is dispatched as a
 * scheduler Event method name, so each one must exist on the real Event
 * class (the ManagesFrequencies vocabulary).
 */
final class CadenceEnumTest extends TestCase
{
    #[Test]
    public function every_case_value_is_a_real_scheduler_event_method(): void
    {
        foreach (Cadence::cases() as $case) {
            $this->assertTrue(
                method_exists(Event::class, $case->value),
                sprintf(
                    'Cadence::%s maps to Event::%s(), which does not exist on %s — the enum has drifted from laravel',
                    $case->name,
                    $case->value,
                    Event::class,
                ),
            );
        }
    }

    #[Test]
    public function values_resolve_back_through_try_from(): void
    {
        foreach (Cadence::cases() as $case) {
            $this->assertSame($case, Cadence::tryFrom($case->value));
        }
    }

    #[Test]
    public function it_covers_the_standard_frequency_spectrum(): void
    {
        $values = array_column(Cadence::cases(), 'value');

        // spot-check the anchor cases each end of the spectrum
        $this->assertContains('everySecond', $values);
        $this->assertContains('everyMinute', $values);
        $this->assertContains('hourly', $values);
        $this->assertContains('daily', $values);
        $this->assertContains('weekly', $values);
        $this->assertContains('monthly', $values);
        $this->assertContains('quarterly', $values);
        $this->assertContains('yearly', $values);
    }
}
