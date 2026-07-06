<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Support;

use Illuminate\Console\Scheduling\CacheEventMutex;
use Illuminate\Console\Scheduling\Event;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Package\Tools\Enums\Cadence;
use Simtabi\Laranail\Package\Tools\Support\Definitions\ScheduledCommandDefinition;
use Simtabi\Laranail\Package\Tools\Support\Scheduling\CronBuilder;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

/**
 * the scheduled-command definition: two-tier __call dispatch (cron-first,
 * event-deferred fallback), cadence shorthands, config gating, and the
 * schedule-time applyTo/shouldSchedule contract.
 */
final class ScheduledCommandDefinitionTest extends TestCase
{
    #[Test]
    public function cron_vocabulary_calls_forward_to_the_builder(): void
    {
        $definition = ScheduledCommandDefinition::make('pkg:sync')->daily();

        $this->assertInstanceOf(ScheduledCommandDefinition::class, $definition);
        $this->assertSame('0 0 * * *', $definition->toArray()['cron']['expression']);
        $this->assertSame([], $definition->toArray()['deferred_calls']);
    }

    #[Test]
    public function non_cron_calls_are_deferred_and_leave_the_builder_untouched(): void
    {
        $definition = ScheduledCommandDefinition::make('pkg:sync')->withoutOverlapping();

        $this->assertNull($definition->toArray()['cron']);
        $this->assertSame(
            [['method' => 'withoutOverlapping', 'args' => []]],
            $definition->toArray()['deferred_calls'],
        );
    }

    #[Test]
    public function cron_and_event_calls_mix_fluently(): void
    {
        $definition = ScheduledCommandDefinition::make('pkg:sync')
            ->weekdays()
            ->withoutOverlapping()
            ->at('02:00');

        $array = $definition->toArray();

        $this->assertSame('0 2 * * 1-5', $array['cron']['expression']);
        $this->assertSame([['method' => 'withoutOverlapping', 'args' => []]], $array['deferred_calls']);
    }

    #[Test]
    public function cron_seeds_the_builder_with_a_raw_expression(): void
    {
        $definition = ScheduledCommandDefinition::make('pkg:sync')->cron('0 2 * * *');

        $this->assertSame(['expression' => '0 2 * * *'], $definition->toArray()['cron']);
    }

    #[Test]
    public function cron_normalizes_a_cron_expressible(): void
    {
        $definition = ScheduledCommandDefinition::make('pkg:sync')
            ->cron(CronBuilder::make()->at('02:00'));

        $this->assertSame(['expression' => '0 2 * * *'], $definition->toArray()['cron']);
    }

    #[Test]
    public function cadence_enum_without_a_cron_form_defers_to_the_event(): void
    {
        // hourly is not a CronBuilder method, so it must land in the queue
        $definition = ScheduledCommandDefinition::make('pkg:sync')->cadence(Cadence::Hourly);

        $array = $definition->toArray();

        $this->assertNull($array['cron']);
        $this->assertSame([['method' => 'hourly', 'args' => []]], $array['deferred_calls']);
    }

    #[Test]
    public function cadence_enum_with_a_cron_form_routes_to_the_builder(): void
    {
        $definition = ScheduledCommandDefinition::make('pkg:sync')->cadence(Cadence::Daily);

        $array = $definition->toArray();

        $this->assertSame('0 0 * * *', $array['cron']['expression']);
        $this->assertSame([], $array['deferred_calls']);
    }

    #[Test]
    public function cadence_method_string_with_argument_defers_the_call(): void
    {
        $definition = ScheduledCommandDefinition::make('pkg:sync')->cadence('dailyAt:02:00');

        $this->assertSame(
            [['method' => 'dailyAt', 'args' => ['02:00']]],
            $definition->toArray()['deferred_calls'],
        );
    }

    #[Test]
    public function cadence_string_coerces_numeric_arguments_to_ints(): void
    {
        $definition = ScheduledCommandDefinition::make('pkg:sync')->cadence('twiceDaily:1,13');

        $this->assertSame(
            [['method' => 'twiceDaily', 'args' => [1, 13]]],
            $definition->toArray()['deferred_calls'],
        );
    }

    #[Test]
    public function cadence_detects_a_raw_cron_expression(): void
    {
        $definition = ScheduledCommandDefinition::make('pkg:sync')->cadence('0 2 * * *');

        $this->assertSame(['expression' => '0 2 * * *'], $definition->toArray()['cron']);
        $this->assertSame([], $definition->toArray()['deferred_calls']);
    }

    #[Test]
    public function cadence_records_a_closure_for_replay(): void
    {
        $definition = ScheduledCommandDefinition::make('pkg:sync')
            ->cadence(static function (): void {});

        $this->assertSame(['closure'], $definition->toArray()['deferred_calls']);
    }

    #[Test]
    public function cadence_from_config_is_stored_for_schedule_time(): void
    {
        $definition = ScheduledCommandDefinition::make('pkg:sync')
            ->cadenceFromConfig('pkg.schedule.cadence', Cadence::Hourly);

        $this->assertSame(
            ['key' => 'pkg.schedule.cadence', 'default' => 'hourly'],
            $definition->toArray()['cadence_config'],
        );
    }

    #[Test]
    public function when_config_stores_a_truthy_gate(): void
    {
        $definition = ScheduledCommandDefinition::make('pkg:sync')->whenConfig('pkg.enabled', false);

        $this->assertSame(
            ['key' => 'pkg.enabled', 'default' => false, 'mode' => 'truthy'],
            $definition->toArray()['gate'],
        );
    }

    #[Test]
    public function when_config_not_null_stores_a_not_null_gate(): void
    {
        $definition = ScheduledCommandDefinition::make('pkg:sync')->whenConfigNotNull('pkg.cadence');

        $this->assertSame(
            ['key' => 'pkg.cadence', 'default' => true, 'mode' => 'not_null'],
            $definition->toArray()['gate'],
        );
    }

    #[Test]
    public function command_returns_the_command_string(): void
    {
        $this->assertSame('pkg:sync', ScheduledCommandDefinition::make('pkg:sync')->command());
    }

    #[Test]
    public function apply_to_defaults_a_bare_definition_to_daily(): void
    {
        $event = $this->makeSpyEvent();

        ScheduledCommandDefinition::make('pkg:sync')->applyTo($event);

        $this->assertSame([['cron', '0 0 * * *']], $event->recorded);
    }

    #[Test]
    public function apply_to_skips_cron_when_only_event_calls_were_made(): void
    {
        $event = $this->makeSpyEvent();

        ScheduledCommandDefinition::make('pkg:sync')->withoutOverlapping(30)->applyTo($event);

        $this->assertSame([['withoutOverlapping', 30]], $event->recorded);
    }

    #[Test]
    public function apply_to_applies_cron_then_replays_deferred_calls(): void
    {
        $event = $this->makeSpyEvent();

        ScheduledCommandDefinition::make('pkg:sync')
            ->at('02:00')
            ->withoutOverlapping()
            ->applyTo($event);

        $this->assertSame([['cron', '0 2 * * *'], ['withoutOverlapping', 1440]], $event->recorded);
    }

    #[Test]
    public function apply_to_throws_for_a_deferred_method_the_event_lacks(): void
    {
        $definition = ScheduledCommandDefinition::make('pkg:sync')->definitelyNotAnEventMethod();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('deferred call "definitelyNotAnEventMethod" does not exist');

        $definition->applyTo($this->makeSpyEvent());
    }

    #[Test]
    public function should_schedule_defaults_to_true(): void
    {
        $this->assertTrue(ScheduledCommandDefinition::make('pkg:sync')->shouldSchedule());
    }

    #[Test]
    public function should_schedule_honours_a_truthy_gate(): void
    {
        config()->set('pkg.enabled', false);
        $this->assertFalse(
            ScheduledCommandDefinition::make('pkg:sync')->whenConfig('pkg.enabled')->shouldSchedule(),
        );

        config()->set('pkg.enabled', true);
        $this->assertTrue(
            ScheduledCommandDefinition::make('pkg:sync')->whenConfig('pkg.enabled')->shouldSchedule(),
        );
    }

    #[Test]
    public function should_schedule_honours_a_not_null_gate(): void
    {
        config()->set('pkg.cadence', false);
        $this->assertTrue(
            ScheduledCommandDefinition::make('pkg:sync')->whenConfigNotNull('pkg.cadence')->shouldSchedule(),
        );

        config()->set('pkg.cadence');
        $this->assertFalse(
            ScheduledCommandDefinition::make('pkg:sync')->whenConfigNotNull('pkg.cadence')->shouldSchedule(),
        );
    }

    #[Test]
    public function config_cadence_with_a_missing_key_and_null_default_skips_scheduling(): void
    {
        $definition = ScheduledCommandDefinition::make('pkg:sync')->cadenceFromConfig('pkg.absent');

        $this->assertFalse($definition->shouldSchedule());
    }

    #[Test]
    public function config_cadence_falls_back_to_the_default_and_applies_it(): void
    {
        $definition = ScheduledCommandDefinition::make('pkg:sync')
            ->cadenceFromConfig('pkg.absent', Cadence::Hourly);

        $this->assertTrue($definition->shouldSchedule());
        $this->assertSame([['method' => 'hourly', 'args' => []]], $definition->toArray()['deferred_calls']);
    }

    #[Test]
    public function config_cadence_applies_a_configured_expression(): void
    {
        config()->set('pkg.schedule', '0 3 * * *');
        $definition = ScheduledCommandDefinition::make('pkg:sync')->cadenceFromConfig('pkg.schedule');

        $this->assertTrue($definition->shouldSchedule());
        $this->assertSame(['expression' => '0 3 * * *'], $definition->toArray()['cron']);
    }

    #[Test]
    public function config_cadence_of_false_disables_scheduling(): void
    {
        config()->set('pkg.schedule', false);

        $this->assertFalse(
            ScheduledCommandDefinition::make('pkg:sync')->cadenceFromConfig('pkg.schedule')->shouldSchedule(),
        );
    }

    #[Test]
    public function config_cadence_fails_closed_on_a_malformed_value(): void
    {
        config()->set('pkg.schedule', 123);

        $this->assertFalse(
            ScheduledCommandDefinition::make('pkg:sync')->cadenceFromConfig('pkg.schedule')->shouldSchedule(),
        );
    }

    #[Test]
    public function it_serializes_to_array_and_json_with_closure_placeholders(): void
    {
        $definition = ScheduledCommandDefinition::make('pkg:sync')
            ->daily()
            ->withoutOverlapping()
            ->configure(static function (): void {})
            ->whenConfig('pkg.enabled');

        $array = $definition->toArray();

        $this->assertSame('pkg:sync', $array['command']);
        $this->assertSame('0 0 * * *', $array['cron']['expression']);
        $this->assertSame(
            [['method' => 'withoutOverlapping', 'args' => []], 'closure'],
            $array['deferred_calls'],
        );
        $this->assertSame(['key' => 'pkg.enabled', 'default' => true, 'mode' => 'truthy'], $array['gate']);
        $this->assertNull($array['cadence_config']);
        $this->assertSame(json_encode($array), $definition->toJson());
        $this->assertSame($array, $definition->jsonSerialize());
    }

    private function makeSpyEvent(): SpyScheduleEvent
    {
        return new SpyScheduleEvent(
            new CacheEventMutex($this->app->make('cache')),
            'php artisan pkg:sync',
        );
    }
}

/**
 * applyTo() is typed against the real Event, so the spy extends it and
 * overrides the methods under observation to record instead of act.
 */
final class SpyScheduleEvent extends Event
{
    /** @var list<array{0: string, 1?: mixed}> */
    public array $recorded = [];

    #[Override]
    public function cron($expression)
    {
        $this->recorded[] = ['cron', $expression];

        return $this;
    }

    #[Override]
    public function withoutOverlapping($expiresAt = 1440, $releaseOnTerminationSignals = true)
    {
        $this->recorded[] = ['withoutOverlapping', $expiresAt];

        return $this;
    }
}
