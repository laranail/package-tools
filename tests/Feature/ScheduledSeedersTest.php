<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Seeder;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\Package\Tools\Enums\Cadence;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\Package\Tools\Providers\PackageToolsServiceProvider;
use Simtabi\Laranail\Package\Tools\Support\Definitions\AutoSeederDefinition;
use Simtabi\Laranail\Package\Tools\Support\Scheduling\TimeOfDay;

final class ScheduledSeedersTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            PackageToolsServiceProvider::class,
            ScheduledSeederProvider::class,
        ];
    }

    /**
     * @return list<Event>
     */
    private function seedEvents(): array
    {
        return array_values(array_filter(
            $this->app->make(Schedule::class)->events(),
            static fn (Event $event): bool => str_contains((string) $event->command, 'laranail::package-tools.seed'),
        ));
    }

    public function test_a_cadenced_definition_lands_on_the_schedule(): void
    {
        $events = $this->seedEvents();

        $this->assertCount(2, $events);
    }

    public function test_scheduled_at_maps_to_daily_at(): void
    {
        $expressions = array_map(static fn (Event $e): string => $e->expression, $this->seedEvents());

        $this->assertContains('0 2 * * *', $expressions);   // scheduledAt(02:00)
        $this->assertContains('0 0 * * *', $expressions);   // Cadence::Daily
    }

    public function test_the_scheduled_command_carries_key_and_provenance_but_no_mode_flag(): void
    {
        foreach ($this->seedEvents() as $event) {
            $command = (string) $event->command;

            $this->assertStringContainsString('--key=', $command);
            $this->assertStringContainsString('--scheduled', $command);
            // The bundle's own runsInBackground() decides the mode — the
            // scheduler must not force one.
            $this->assertStringNotContainsString('--queued', $command);
            $this->assertStringNotContainsString('--sync', $command);
        }
    }

    public function test_without_overlapping_applies_to_the_schedule_event(): void
    {
        $overlapping = array_filter(
            $this->seedEvents(),
            static fn (Event $event): bool => $event->withoutOverlapping,
        );

        $this->assertCount(1, $overlapping);
    }
}

final class ScheduledFixtureSeeder extends Seeder {}

final class ScheduledSeederProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->setName('test/scheduled-seeders');
        $package->basePath = sys_get_temp_dir();

        $package->hasPackageSeeders(
            AutoSeederDefinition::make('t/nightly')
                ->seeders([ScheduledFixtureSeeder::class])
                ->scheduledAt(TimeOfDay::at(2))
                ->withoutOverlapping(),
        );

        $package->hasPackageSeeders(
            AutoSeederDefinition::make('t/daily-enum')
                ->seeders([ScheduledFixtureSeeder::class])
                ->cadence(Cadence::Daily),
        );
    }
}
