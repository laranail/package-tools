<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider;

use Illuminate\Console\Scheduling\Schedule;

trait ProcessScheduledCommands
{
    /**
     * Apply the package's scheduled-command definitions and raw schedule
     * callbacks once the Schedule resolves (console only, after every
     * provider booted) — config gates and cadences evaluate then, never
     * earlier.
     */
    protected function bootPackageScheduledCommands(): self
    {
        if ($this->package->getScheduledCommands() === [] && $this->package->getScheduleCallbacks() === []) {
            return $this;
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            foreach ($this->package->getScheduledCommands() as $definition) {
                if (! $definition->shouldSchedule()) {
                    continue;
                }

                $definition->applyTo($schedule->command($definition->command()));
            }

            foreach ($this->package->getScheduleCallbacks() as $callback) {
                $callback($schedule);
            }
        });

        return $this;
    }
}
