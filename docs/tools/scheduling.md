# Scheduling

`$package->registerScheduledCommand(...)` declares scheduled tasks, and `$package->schedulesUsing(...)` is the raw-callback escape hatch — both applied by the provider once Laravel's `Schedule` resolves (console only, after every provider has booted), backed by the fluent `Simtabi\Laranail\Package\Tools\Support\Definitions\ScheduledCommandDefinition`.

## Quick start

```php
use Simtabi\Laranail\Package\Tools\Enums\Cadence;

public function configurePackage(Package $package): void
{
    $package
        ->registerScheduledCommand('acme:prune', Cadence::Daily)
        ->registerScheduledCommand(
            ScheduledCommandDefinition::make('acme:sync')
                ->cadence(Cadence::Hourly)
                ->withoutOverlapping()
                ->onOneServer()
        );
}
```

## Cadences

The cadence accepts a `Cadence` enum case, a raw cron string, a scheduler-method string, a `CronExpressible`, or a closure:

```php
->registerScheduledCommand('acme:a', Cadence::EveryFiveMinutes)
->registerScheduledCommand('acme:b', '0 2 * * *')            // raw cron
->registerScheduledCommand(
    ScheduledCommandDefinition::make('acme:c')
        ->cadence(Cadence::Weekly)
        ->at(TimeOfDay::at(2))                                // compose time
        ->weekdays()
)
```

Config-driven cadence (read at schedule time, so the host can retune without a deploy):

```php
ScheduledCommandDefinition::make('acme:report')
    ->cadenceFromConfig('acme.report.cadence', Cadence::Daily)   // null/false → not scheduled
    ->whenConfig('acme.report.enabled');                         // config gate
```

## The scheduler callback

`schedulesUsing(Closure $callback)` hands you the real `Illuminate\Console\Scheduling\Schedule` for anything the fluent definition doesn't cover — job scheduling, `->then()`/`->onSuccess()` hooks, ping URLs, closures on the scheduler, etc.:

```php
use Illuminate\Console\Scheduling\Schedule;

$package->schedulesUsing(function (Schedule $schedule): void {
    $schedule->job(new PruneReportsJob)
        ->weekly()
        ->onOneServer()
        ->onSuccess(fn () => Log::info('reports pruned'));

    $schedule->call(fn () => Cache::tags('acme')->flush())
        ->dailyAt('03:30');
});
```

The callback runs once the `Schedule` resolves (console only), after every provider has booted — so all packages' schedules are registered together.

## Reference

| Method | Effect |
|---|---|
| `registerScheduledCommand(ScheduledCommandDefinition\|string $command, Cadence\|CronExpressible\|string\|Closure $cadence = Cadence::Daily)` | schedule one command |
| `registerScheduledCommands(array $commands)` | many at once — definitions, plain strings, or `[$command => $cadence]` pairs |
| `schedulesUsing(Closure $callback)` | raw escape hatch: `fn (Schedule $schedule) => …` |

`ScheduledCommandDefinition` forwards the whole Laravel scheduler vocabulary (`daily()`, `everyFiveMinutes()`, `weekdays()`, `withoutOverlapping()`, `onOneServer()`, `runInBackground()`, `timezone()`, `environments()`, `between()`, …) plus `cadence()`, `cadenceFromConfig()`, `whenConfig()`, and `configure(fn (Event $e) => …)`.

> A misconfigured cadence (an unknown scheduler method) throws **loudly** when the `Schedule` resolves — this is intentional fail-fast: a scheduling typo is an author bug that should surface immediately, not be silently dropped.

---

[← Docs index](../../README.md#documentation)
