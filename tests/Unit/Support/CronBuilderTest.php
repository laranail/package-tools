<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Support;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\Package\Tools\Contracts\CronExpressible;
use Simtabi\Laranail\Package\Tools\Enums\Weekday;
use Simtabi\Laranail\Package\Tools\Support\Scheduling\CronBuilder;
use Simtabi\Laranail\Package\Tools\Support\Scheduling\TimeOfDay;

/**
 * the fluent cron designer: field setters and validation, the frequency
 * vocabulary, raw-expression seeding, and serialization.
 */
final class CronBuilderTest extends TestCase
{
    #[Test]
    public function it_defaults_to_every_minute_untouched(): void
    {
        $builder = CronBuilder::make();

        $this->assertSame('* * * * *', $builder->toExpression());
        $this->assertFalse($builder->isTouched());
    }

    #[Test]
    public function it_implements_cron_expressible(): void
    {
        $this->assertInstanceOf(CronExpressible::class, CronBuilder::make());
    }

    #[Test]
    public function each_field_setter_writes_its_own_position(): void
    {
        $this->assertSame('30 * * * *', CronBuilder::make()->minute(30)->toExpression());
        $this->assertSame('* 2 * * *', CronBuilder::make()->hour(2)->toExpression());
        $this->assertSame('* * 15 * *', CronBuilder::make()->dayOfMonth(15)->toExpression());
        $this->assertSame('* * * 6 *', CronBuilder::make()->month(6)->toExpression());
        $this->assertSame('* * * * 3', CronBuilder::make()->dayOfWeek(3)->toExpression());
    }

    #[Test]
    public function fields_accept_int_list_range_and_step_forms(): void
    {
        $this->assertSame('0,30 * * * *', CronBuilder::make()->minute([0, 30])->toExpression());
        $this->assertSame('5-20 * * * *', CronBuilder::make()->minute('5-20')->toExpression());
        $this->assertSame('*/5 * * * *', CronBuilder::make()->minute('*/5')->toExpression());
        $this->assertSame('1-30/2 * * * *', CronBuilder::make()->minute('1-30/2')->toExpression());
    }

    #[Test]
    public function day_of_week_accepts_weekday_enums(): void
    {
        $this->assertSame('* * * * 1', CronBuilder::make()->dayOfWeek(Weekday::Monday)->toExpression());
        $this->assertSame(
            '* * * * 1,4',
            CronBuilder::make()->dayOfWeek([Weekday::Monday, Weekday::Thursday])->toExpression(),
        );
    }

    #[Test]
    public function minute_rejects_60(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('outside 0-59');

        CronBuilder::make()->minute(60);
    }

    #[Test]
    public function day_of_month_rejects_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('outside 1-31');

        CronBuilder::make()->dayOfMonth(0);
    }

    #[Test]
    public function fields_reject_garbage_strings(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not a valid cron field value');

        CronBuilder::make()->minute('* *');
    }

    #[Test]
    public function fields_reject_empty_lists(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be empty');

        CronBuilder::make()->minute([]);
    }

    #[Test]
    public function at_accepts_a_string_time(): void
    {
        $this->assertSame('0 2 * * *', CronBuilder::make()->at('02:00')->toExpression());
    }

    #[Test]
    public function at_accepts_a_time_of_day(): void
    {
        $this->assertSame('30 17 * * *', CronBuilder::make()->at(TimeOfDay::pm(5, 30))->toExpression());
    }

    #[Test]
    public function daily_is_midnight(): void
    {
        $this->assertSame('0 0 * * *', CronBuilder::make()->daily()->toExpression());
    }

    #[Test]
    public function daily_keeps_an_already_set_time(): void
    {
        $this->assertSame('0 2 * * *', CronBuilder::make()->at('02:00')->daily()->toExpression());
    }

    #[Test]
    public function weekly_defaults_to_sunday(): void
    {
        $this->assertSame('0 0 * * 0', CronBuilder::make()->weekly()->toExpression());
    }

    #[Test]
    public function weekly_accepts_a_weekday(): void
    {
        $this->assertSame('0 0 * * 1', CronBuilder::make()->weekly(Weekday::Monday)->toExpression());
    }

    #[Test]
    public function monthly_accepts_a_day_of_month(): void
    {
        $this->assertSame('0 0 1 * *', CronBuilder::make()->monthly()->toExpression());
        $this->assertSame('0 0 15 * *', CronBuilder::make()->monthly(15)->toExpression());
    }

    #[Test]
    public function quarterly_runs_the_first_of_each_quarter(): void
    {
        $this->assertSame('0 0 1 1,4,7,10 *', CronBuilder::make()->quarterly()->toExpression());
    }

    #[Test]
    public function yearly_runs_january_first(): void
    {
        $this->assertSame('0 0 1 1 *', CronBuilder::make()->yearly()->toExpression());
    }

    #[Test]
    public function every_minutes_sets_a_minute_step(): void
    {
        $this->assertSame('*/15 * * * *', CronBuilder::make()->everyMinutes(15)->toExpression());
    }

    #[Test]
    public function every_hours_sets_an_hour_step_at_minute_zero(): void
    {
        $this->assertSame('0 */4 * * *', CronBuilder::make()->everyHours(4)->toExpression());
    }

    #[Test]
    public function every_days_and_every_months_set_steps_at_midnight(): void
    {
        $this->assertSame('0 0 */2 * *', CronBuilder::make()->everyDays(2)->toExpression());
        $this->assertSame('0 0 1 */3 *', CronBuilder::make()->everyMonths(3)->toExpression());
    }

    #[Test]
    public function steps_reject_values_outside_the_field_width(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('step must be 1-59');

        CronBuilder::make()->everyMinutes(60);
    }

    #[Test]
    public function twice_weekly_defaults_to_monday_and_thursday(): void
    {
        $this->assertSame('0 0 * * 1,4', CronBuilder::make()->twiceWeekly()->toExpression());
    }

    #[Test]
    public function twice_monthly_defaults_to_the_first_and_sixteenth(): void
    {
        $this->assertSame('0 0 1,16 * *', CronBuilder::make()->twiceMonthly()->toExpression());
    }

    #[Test]
    public function bi_weekly_honestly_means_twice_weekly(): void
    {
        $this->assertSame(
            CronBuilder::make()->twiceWeekly()->toExpression(),
            CronBuilder::make()->biWeekly()->toExpression(),
        );
    }

    #[Test]
    public function bi_monthly_honestly_means_twice_monthly(): void
    {
        $this->assertSame(
            CronBuilder::make()->twiceMonthly()->toExpression(),
            CronBuilder::make()->biMonthly()->toExpression(),
        );
    }

    #[Test]
    public function weekdays_is_monday_through_friday(): void
    {
        $this->assertSame('0 0 * * 1-5', CronBuilder::make()->weekdays()->toExpression());
    }

    #[Test]
    public function weekends_is_saturday_and_sunday(): void
    {
        $this->assertSame('0 0 * * 6,0', CronBuilder::make()->weekends()->toExpression());
    }

    #[Test]
    public function from_expression_round_trips_verbatim(): void
    {
        $builder = CronBuilder::make()->fromExpression('0 2 * * 1-5');

        $this->assertSame('0 2 * * 1-5', $builder->toExpression());
        $this->assertTrue($builder->isTouched());
    }

    #[Test]
    public function from_expression_normalizes_whitespace(): void
    {
        $this->assertSame('0 2 * * *', CronBuilder::make()->fromExpression("0  2 *\t* *")->toExpression());
    }

    #[Test]
    public function field_setters_are_rejected_after_from_expression(): void
    {
        $builder = CronBuilder::make()->fromExpression('0 2 * * *');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('field setters are unavailable');

        $builder->minute(5);
    }

    #[Test]
    public function from_expression_rejects_non_five_field_input(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not a 5-field cron expression');

        CronBuilder::make()->fromExpression('* * *');
    }

    #[Test]
    public function is_touched_flips_on_any_field_setter(): void
    {
        $this->assertFalse(CronBuilder::make()->isTouched());
        $this->assertTrue(CronBuilder::make()->minute(0)->isTouched());
    }

    #[Test]
    public function it_serializes_fields_to_array_and_json(): void
    {
        $builder = CronBuilder::make()->at('02:30')->dayOfWeek(Weekday::Monday);
        $expected = [
            'minute' => '30',
            'hour' => '2',
            'day_of_month' => '*',
            'month' => '*',
            'day_of_week' => '1',
            'expression' => '30 2 * * 1',
        ];

        $this->assertSame($expected, $builder->toArray());
        $this->assertSame(json_encode($expected), $builder->toJson());
    }

    #[Test]
    public function a_raw_seeded_builder_serializes_to_the_expression_only(): void
    {
        $builder = CronBuilder::make()->fromExpression('0 2 * * *');

        $this->assertSame(['expression' => '0 2 * * *'], $builder->toArray());
    }
}
