# Action lifecycle events

`PackageAction{Started,Succeeded,Failed}` is one reusable event family for every package action — migrations, seeders, jobs, schedules, installs, and your own custom work — routed through the `PackageActions` facade so a failure is always logged, dispatched once, and observable anywhere.

## Why it exists

Seeders already had a full lifecycle (`PackageSeedingStarted/Completed/Failed` plus per-seeder events), but migrations, jobs, and schedules had none, and seeder failures on the autorun / `db:seed` resolver / queued-job paths were swallowed silently. Laravel itself dispatches **no** migration-failure event. This family gives every action type the same started / succeeded / failed surface, with the failure taxonomy carried by `FailureReason`.

## The events

All three extend `Simtabi\Laranail\Package\Tools\Events\PackageActionEvent` and are serialization-safe (the originating exception is captured as class + message strings, never a live `Throwable`, so listeners may queue).

| Event | Fired | Extra fields |
|---|---|---|
| `PackageActionStarted` | before the work | — |
| `PackageActionSucceeded` | after clean completion | `?float $durationMs` |
| `PackageActionFailed` | on failure | `FailureReason $reason`, `?string $exceptionClass`, `string $message` |

Shared fields: `PackageActionType $type`, `string $action`, `?string $packageName`, `array $context`, `?SeederExecutionMode $mode`.

### Enums

- `PackageActionType`: `Migration`, `Seeder`, `Job`, `Schedule`, `Install`, `Custom`.
- `FailureReason`: `Failed`, `Interrupted`, `Cancelled`, `TimedOut`, `Unknown`. `FailureReason::fromThrowable()` maps queue timeout / max-attempt exhaustion to `TimedOut`, everything else to `Failed` — the one place a throwable becomes a reason.

## The `PackageActions` facade

`Simtabi\Laranail\Package\Tools\Facades\PackageActions` is a container singleton reachable from anywhere — a provider, a command, a job, or plain application code.

```php
use Simtabi\Laranail\Package\Tools\Enums\PackageActionType;
use Simtabi\Laranail\Package\Tools\Facades\PackageActions;

// Report a custom failure
PackageActions::fail(PackageActionType::Custom, 'nightly-export', 'acme/reports', 'disk full');

// Wrap a unit of work so its start → success|failure lifecycle is emitted automatically
$result = PackageActions::track(PackageActionType::Custom, 'rebuild-index', 'acme/search', function () {
    return $this->rebuild();
});

// Log to a specific package's own logfile
PackageActions::forPackage($package->log())->fromThrowable(
    PackageActionType::Custom, 'sync', 'acme/x', $exception,
);
```

Failures are **always logged at error** regardless of the dispatch gate; the high-frequency start/success stream is logged at debug and dispatched only when the lifecycle gate is on.

## Listening

`PackageActionStarted` / `PackageActionSucceeded` are plain events — listen to them directly. For failures, extend `HandlesPackageActionFailure` to react per action type:

```php
use Simtabi\Laranail\Package\Tools\Events\PackageActionFailed;
use Simtabi\Laranail\Package\Tools\Listeners\HandlesPackageActionFailure;

final class NotifyOnPackageFailure extends HandlesPackageActionFailure
{
    protected function onMigration(PackageActionFailed $event): void { /* … */ }
    protected function onSeeder(PackageActionFailed $event): void { /* … */ }
}

// register it like any listener
$package->registerEventListener(PackageActionFailed::class, NotifyOnPackageFailure::class);
```

The reporter owns logging, so there is no built-in logging listener to double up.

## Seeders: two layers

The 8 bespoke seeder events are unchanged (the seeder-specific **detail** layer). The unified family fires **alongside** them at the bundle level (the cross-type layer) — so a listener that only cares about seeders keeps using `PackageSeedingFailed`, while a cross-type dashboard subscribes once to `PackageActionFailed` and sees every action type. The autorun / resolver / queued-job paths now also report failures instead of swallowing them.

## Migrations

Because Laravel emits no migration-failure event, the migrator is decorated (`FailureAwareMigrator`) console-side to emit the full lifecycle with the real migration name and exception. The decoration is **composition-safe**: if another package has already decorated the migrator, package-tools leaves it untouched and falls back to a conflict-free event-based detector (`MigrationFailureDetector`) so it never clobbers another package and always reports at best-available fidelity.

## Configuration

Two independent gates in `config/package-tools.php` (a failure is always *logged* regardless — the `failures` gate only controls event dispatch):

```php
'events' => [
    'lifecycle' => ['enabled' => env('PACKAGE_TOOLS_LIFECYCLE_EVENTS', true)],
    'failures'  => ['enabled' => env('PACKAGE_TOOLS_FAILURE_EVENTS', true)],
],
'migrations' => [
    'failure_detection' => ['enabled' => env('PACKAGE_TOOLS_MIGRATION_FAILURE_DETECTION', true)],
],
```

---

[← Docs index](../../README.md#documentation)
