# Scheduling a command

Declare the cadence beside the command, not in the application's `routes/console.php`:

```php
$package->registerScheduledCommand(
    ScheduledCommandDefinition::make(SyncCommand::class)
        ->cadence(Cadence::Hourly)
        ->withoutOverlapping(),
);
```

The application gets the schedule by installing the package. Nothing to copy, and
nothing that drifts when the cadence changes.

## `Cadence` is a typed case, not a string

Its values are the scheduler's own `Event` method names, so the dispatch pipeline
consumes them directly and a config string resolves through `tryFrom()` before
anything parses it. A typo becomes a null, not a silent no-op.

## Overlap is the failure you will actually hit

A sync that usually takes 40 seconds will one day take 20 minutes, and the
scheduler will start a second one on top of it. `withoutOverlapping()` is close to
always correct for anything that touches shared state.

## More

[Scheduling](../tools/scheduling.md) · [Seeding](../seeding.md)

---

[← Docs index](../../README.md#documentation)
