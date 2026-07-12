<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Concerns;

use Illuminate\Console\Scheduling\Schedule;
use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Package\Tools\Concerns\Package\HasScheduledCommands;
use Simtabi\Laranail\Package\Tools\Enums\Cadence;
use Simtabi\Laranail\Package\Tools\Support\Definitions\ScheduledCommandDefinition;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

/**
 * declarative scheduler registration: shorthand wrapping into definitions
 * with the cadence applied, and every accepted batch shape.
 */
final class HasScheduledCommandsTest extends TestCase
{
    use HasScheduledCommands;

    #[Test]
    public function a_command_string_is_wrapped_into_a_definition_with_daily_default(): void
    {
        $this->registerScheduledCommand('pkg:sync');

        $definitions = $this->getScheduledCommands();

        $this->assertCount(1, $definitions);
        $this->assertSame('pkg:sync', $definitions[0]->command());
        // default cadence is daily, which routes to the cron builder
        $this->assertSame('0 0 * * *', $definitions[0]->toArray()['cron']['expression']);
    }

    #[Test]
    public function a_shorthand_cadence_is_applied_to_the_wrapped_definition(): void
    {
        $this->registerScheduledCommand('pkg:sync', Cadence::Hourly);

        $array = $this->getScheduledCommands()[0]->toArray();

        // hourly has no cron form, so it lands in the deferred queue
        $this->assertSame([['method' => 'hourly', 'args' => []]], $array['deferred_calls']);
    }

    #[Test]
    public function a_prebuilt_definition_is_stored_as_is(): void
    {
        $definition = ScheduledCommandDefinition::make('pkg:sync')->weekdays();

        $this->registerScheduledCommand($definition);

        $this->assertSame([$definition], $this->getScheduledCommands());
    }

    #[Test]
    public function batch_registration_accepts_a_list_of_definitions(): void
    {
        $first = ScheduledCommandDefinition::make('pkg:one');
        $second = ScheduledCommandDefinition::make('pkg:two');

        $this->registerScheduledCommands([$first, $second]);

        $this->assertSame([$first, $second], $this->getScheduledCommands());
    }

    #[Test]
    public function batch_registration_accepts_plain_command_strings(): void
    {
        $this->registerScheduledCommands(['pkg:one', 'pkg:two']);

        $definitions = $this->getScheduledCommands();

        $this->assertCount(2, $definitions);
        $this->assertSame('pkg:one', $definitions[0]->command());
        $this->assertSame('pkg:two', $definitions[1]->command());
        // string entries get the daily default
        $this->assertSame('0 0 * * *', $definitions[0]->toArray()['cron']['expression']);
    }

    #[Test]
    public function batch_registration_accepts_a_command_to_cadence_string_map(): void
    {
        $this->registerScheduledCommands(['pkg:sync' => 'dailyAt:02:00']);

        $array = $this->getScheduledCommands()[0]->toArray();

        $this->assertSame('pkg:sync', $this->getScheduledCommands()[0]->command());
        $this->assertSame([['method' => 'dailyAt', 'args' => ['02:00']]], $array['deferred_calls']);
    }

    #[Test]
    public function batch_registration_accepts_a_command_to_cadence_enum_map(): void
    {
        $this->registerScheduledCommands(['pkg:sync' => Cadence::Hourly]);

        $array = $this->getScheduledCommands()[0]->toArray();

        $this->assertSame([['method' => 'hourly', 'args' => []]], $array['deferred_calls']);
    }

    #[Test]
    public function batch_shapes_mix_in_one_call(): void
    {
        $definition = ScheduledCommandDefinition::make('pkg:def');

        $this->registerScheduledCommands([
            $definition,
            'pkg:plain',
            'pkg:mapped' => Cadence::Weekly,
        ]);

        $definitions = $this->getScheduledCommands();

        $this->assertCount(3, $definitions);
        $this->assertSame($definition, $definitions[0]);
        $this->assertSame('pkg:plain', $definitions[1]->command());
        $this->assertSame('pkg:mapped', $definitions[2]->command());
    }

    #[Test]
    public function schedules_using_stores_raw_schedule_callbacks(): void
    {
        $callback = static function (Schedule $schedule): void {};

        $this->schedulesUsing($callback);

        $this->assertSame([$callback], $this->getScheduleCallbacks());
    }

    #[Test]
    public function registration_is_fluent(): void
    {
        $result = $this->registerScheduledCommand('pkg:one')
            ->registerScheduledCommands(['pkg:two'])
            ->schedulesUsing(static function (): void {});

        $this->assertSame($this, $result);
        $this->assertCount(2, $this->getScheduledCommands());
        $this->assertCount(1, $this->getScheduleCallbacks());
    }
}
