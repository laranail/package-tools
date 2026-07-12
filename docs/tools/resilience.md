# Boot-wiring failures

How package-tools handles a failure in a package's own boot wiring, decided by **criticality, not environment** (the same behaviour in dev, CI, and prod). This is one application of the [failure-handling standard](../failure-handling.md).

## The rule

Classify each builder by the state left behind if it fails:

- **Critical** — continuing is unsafe. Wrap, `report()` through the exception handler, and **rethrow** (fail fast). Same in every environment.
- **Degradable** — continuing is safe (reduced). Wrap, report, **record the degraded state, and continue.**

Unclassified defaults to Critical (fail closed). There is no strict/lenient-by-environment toggle — masking a real misconfiguration in production is the worst place to hide it.

## Classification

| Builder | Class |
|---|---|
| `useHttps` | Critical (security) |
| route-model binding (`registerRouteModel`) | Critical (silent 404s / wrong records) |
| closure `addSubscriber(...)` | Critical (unhandled events) |
| `configDecorator(...)` | Critical by default — **author may mark `BootCriticality::Degradable`** |
| view composer / creator registration | Critical |
| `setLocale` | Degradable (cosmetic) |
| boot-time seeder discovery | Degradable |
| schedule config (bad cadence) | Degradable |

```php
use Simtabi\Laranail\Package\Tools\Enums\BootCriticality;

// a cosmetic decoration whose absence is safe → opt into Degradable
$package->configDecorator(
    fn (ConfigDecorator $c) => $c->set('app.name', setting('app_name')),
    BootCriticality::Degradable,
);
```

## Observable degraded state

Degradable failures are recorded on the `BootReport` singleton (redacted — builder name / criticality / exception class, never raw messages) and surfaced by the **`boot:health` doctor check** in `laranail::package-tools.doctor`, so a CI gate catches degradation without a dev-only crash.

```php
// CI gate
$this->assertTrue(app(\Simtabi\Laranail\Package\Tools\Services\Boot\BootReport::class)->isHealthy());
```

A throwing boot builder is wrapped in a `PackageBootException` that names the builder (`$e->builder`, `$e->criticality`, `$e->context()`), preserving the original cause via `getPrevious()`.

## Warnings

`FailurePolicy::warn(subject, context)` logs a tolerated anomaly at warning level (a fired fallback, a near-miss) without reporting or crashing — e.g. an About-section field that fell back, or the migrator's composition fallback.

## Consumer operational wiring

See [failure-handling.md](../failure-handling.md) for the `Exceptions::throttle()` snippet (rule 9), the internal `/health/boot` route (rules 7/11), and the CI gate (rule 12).

## Reusing it

```php
use Simtabi\Laranail\Package\Tools\Support\Resilience\FailurePolicy;
use Simtabi\Laranail\Package\Tools\Enums\BootCriticality;

FailurePolicy::run(fn () => $this->wireSomething(), 'my-critical-step', BootCriticality::Critical);
FailurePolicy::run(fn () => $this->optional(), 'my-optional-step', BootCriticality::Degradable);
```

---

[← Docs index](../../README.md#documentation)
