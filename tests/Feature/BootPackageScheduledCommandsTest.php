<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature;

use Illuminate\Console\Scheduling\Event as ScheduleEvent;
use Illuminate\Console\Scheduling\Schedule;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\Package\Tools\Enums\Cadence;
use Simtabi\Laranail\Package\Tools\Exceptions\ScheduleConfigurationException;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\Package\Tools\Support\Definitions\ScheduledCommandDefinition;

/**
 * scheduled-command definitions must apply once the Schedule resolves —
 * config gates and config-driven cadences evaluate at that moment, never
 * at boot. every test therefore sets config in its body and only THEN
 * resolves the Schedule.
 */
final class BootPackageScheduledCommandsTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [ScheduledCommandsTestPackageProvider::class];
    }

    public function test_daily_definition_schedules_at_midnight(): void
    {
        $event = $this->findEvent('pkg-sched:plain-daily');

        $this->assertNotNull($event);
        $this->assertSame('0 0 * * *', $event->expression);
    }

    public function test_at_time_and_weekdays_compose_into_one_expression(): void
    {
        $event = $this->findEvent('pkg-sched:at-weekdays');

        $this->assertNotNull($event);
        $this->assertSame('30 2 * * 1-5', $event->expression);
    }

    public function test_configure_closure_applies_to_the_real_event(): void
    {
        $event = $this->findEvent('pkg-sched:configured');

        $this->assertNotNull($event);
        $this->assertSame('configured-x', $event->description);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_when_config_on_schedules_the_command(): void
    {
        config()->set('test.sched.gate', true);

        $this->assertNotNull($this->findEvent('pkg-sched:gated'));
    }

    public function test_when_config_off_leaves_the_command_unscheduled(): void
    {
        config()->set('test.sched.gate', false);

        $this->assertNull($this->findEvent('pkg-sched:gated'));
    }

    public function test_when_config_not_null_with_null_config_is_absent(): void
    {
        // key never set: config() returns null
        $this->assertNull($this->findEvent('pkg-sched:notnull'));
    }

    public function test_when_config_not_null_with_a_value_is_present(): void
    {
        config()->set('test.sched.notnull', 'anything');

        $this->assertNotNull($this->findEvent('pkg-sched:notnull'));
    }

    public function test_cadence_from_config_applies_a_named_cadence(): void
    {
        config()->set('test.sched.cadence', 'hourly');

        $event = $this->findEvent('pkg-sched:cadence-value');

        $this->assertNotNull($event);
        $this->assertSame('0 * * * *', $event->expression);
    }

    public function test_cadence_from_config_with_null_value_skips_scheduling(): void
    {
        // no config, no default: an unconfigured cadence means "off"
        $this->assertNull($this->findEvent('pkg-sched:cadence-value'));
    }

    public function test_cadence_from_config_missing_key_uses_the_cadence_default(): void
    {
        $event = $this->findEvent('pkg-sched:cadence-default');

        $this->assertNotNull($event);
        $this->assertSame('0 0 * * *', $event->expression);
    }

    public function test_cadence_from_config_missing_key_and_null_default_is_absent(): void
    {
        $this->assertNull($this->findEvent('pkg-sched:cadence-nulldefault'));
    }

    public function test_cadence_from_config_accepts_a_raw_cron_expression(): void
    {
        config()->set('test.sched.cadence', '15 3 * * *');

        $event = $this->findEvent('pkg-sched:cadence-value');

        $this->assertNotNull($event);
        $this->assertSame('15 3 * * *', $event->expression);
    }

    public function test_schedules_using_closure_receives_the_schedule(): void
    {
        ScheduledCommandsTestPackageProvider::$scheduleFromCallback = null;

        $schedule = $this->app->make(Schedule::class);

        $this->assertSame($schedule, ScheduledCommandsTestPackageProvider::$scheduleFromCallback);
        $this->assertNotNull($this->findEvent('pkg-sched:from-closure'));
    }

    public function test_unknown_cadence_is_wrapped_and_thrown_in_strict_mode(): void
    {
        // the bogus method survives shouldSchedule() (it records a deferred
        // event call) and blows up in DeferredCallQueue::replayOn() when the
        // definition applies to the real event — at Schedule resolution. In
        // strict mode (the default outside production, incl. tests) it is
        // wrapped in a typed exception carrying the command + cause.
        config()->set('test.sched.bad', 'nonsense_method');

        try {
            $this->app->make(Schedule::class);
            $this->fail('expected a ScheduleConfigurationException');
        } catch (ScheduleConfigurationException $e) {
            $this->assertStringContainsString('nonsense_method', $e->getMessage());
            $this->assertArrayHasKey('command', $e->context);
            $this->assertArrayHasKey('reason', $e->context);
        }
    }

    public function test_lenient_mode_logs_and_skips_a_bad_cadence_so_healthy_ones_survive(): void
    {
        // Lenient (production, or explicitly configured): a bad cadence is
        // logged + skipped instead of aborting the whole scheduler, so the
        // package's other scheduled commands still register.
        config()->set('package-tools.scheduling.strict', false);
        config()->set('test.sched.bad', 'nonsense_method');

        // Must not throw.
        $this->app->make(Schedule::class);

        // `twice-daily` is registered AFTER the bad one in the loop, so its
        // presence proves iteration continued past the failure; `from-closure`
        // proves the separate callback loop ran too.
        $this->assertNotNull($this->findEvent('pkg-sched:twice-daily'));
        $this->assertNotNull($this->findEvent('pkg-sched:from-closure'));
    }

    public function test_twice_daily_cadence_string_matches_the_native_scheduler_call(): void
    {
        $event = $this->findEvent('pkg-sched:twice-daily');

        $this->assertNotNull($event);

        // compare against a manually-built event using the native call
        $manual = (new Schedule)->command('manual-reference')->twiceDaily(1, 13);

        $this->assertSame($manual->expression, $event->expression);
    }

    private function findEvent(string $command): ?ScheduleEvent
    {
        $schedule = $this->app->make(Schedule::class);

        foreach ($schedule->events() as $event) {
            if (str_contains((string) $event->command, $command)) {
                return $event;
            }
        }

        return null;
    }
}

final class ScheduledCommandsTestPackageProvider extends PackageServiceProvider
{
    public static ?Schedule $scheduleFromCallback = null;

    public function configurePackage(Package $package): void
    {
        $package->setName('test/scheduled-commands');
        $package->basePath = sys_get_temp_dir();

        $package->registerScheduledCommands([
            ScheduledCommandDefinition::make('pkg-sched:plain-daily')->daily(),
            ScheduledCommandDefinition::make('pkg-sched:at-weekdays')->at('02:30')->weekdays(),
            ScheduledCommandDefinition::make('pkg-sched:configured')
                ->daily()
                ->configure(static fn (ScheduleEvent $event) => $event->name('configured-x')->withoutOverlapping()),
            ScheduledCommandDefinition::make('pkg-sched:gated')->daily()->whenConfig('test.sched.gate'),
            ScheduledCommandDefinition::make('pkg-sched:notnull')->daily()->whenConfigNotNull('test.sched.notnull'),
            ScheduledCommandDefinition::make('pkg-sched:cadence-value')->cadenceFromConfig('test.sched.cadence'),
            ScheduledCommandDefinition::make('pkg-sched:cadence-default')->cadenceFromConfig('test.sched.missing', Cadence::Daily),
            ScheduledCommandDefinition::make('pkg-sched:cadence-nulldefault')->cadenceFromConfig('test.sched.missing2'),
            ScheduledCommandDefinition::make('pkg-sched:bad-cadence')->cadenceFromConfig('test.sched.bad'),
            ScheduledCommandDefinition::make('pkg-sched:twice-daily')->cadence('twiceDaily:1,13'),
        ]);

        $package->schedulesUsing(static function (Schedule $schedule): void {
            self::$scheduleFromCallback = $schedule;
            $schedule->command('pkg-sched:from-closure')->hourly();
        });
    }
}
