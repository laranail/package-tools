# Boot-wiring failures

How package-tools handles a failure in a package's own boot wiring, decided by one question: **does swallowing the failure leave a safe, working state?** Wiring that can't degrade safely fails loud (with an error that names the culprit); wiring that degrades safely is logged and skipped.

## The split

`Simtabi\Laranail\Package\Tools\Support\Resilience\FailurePolicy` provides the two behaviours.

### Fail loud — `rethrowing()`

For boot wiring that does **not** degrade safely. Swallowing it boots a *silently broken* app, which is worse than a crash:

| Builder | If swallowed |
|---|---|
| `useHttps` | serves HTTP when HTTPS was required — a silent security misconfig |
| `setLocale` | boots in the wrong locale |
| `registerRouteModel(fn () => …)` | the binding vanishes, surfacing later as a 404 / wrong-record bug |
| closure `addSubscriber(...)` | events go silently unhandled |

On failure the throwable is wrapped in a `Simtabi\Laranail\Package\Tools\Exceptions\PackageBootException` that names which builder blew up (with the original as `getPrevious()`), then **rethrown** — so boot dies pointing straight at the culprit instead of a bare trace deep in a closure. It behaves the same in every environment: masking a real misconfiguration in production hides it in the worst possible place.

### Degrade safe — `swallow()`

For boot wiring that **does** degrade to a safe, working state:

| Builder | Degrades to |
|---|---|
| `configDecorator(...)` | the undecorated config (still valid) |
| boot-time seeder discovery | no seed data for that bundle |
| view composer / creator registration | the composer simply isn't attached |
| About-section fields | its fallback value (cosmetic) |

On failure it is logged (to the package's own logfile when available, else the default channel) and skipped — one package can't crash host boot, and the app keeps behaving correctly.

## Why not strict/lenient by environment?

An earlier design toggled strict (rethrow) in dev vs lenient (swallow) in prod for these sites. That is the wrong split: it makes production the one place a real misconfiguration gets masked, and gives you divergent failure modes between environments (a thing that crashes cleanly in dev limps along broken in prod). The safe-degradation split above is environment-independent.

## Scheduling

Schedule-configuration failures keep their own policy (`scheduling.strict`): a bad cadence / unknown scheduler method is logged, then rethrown outside production (so authors catch the typo) or skipped in production (so one package can't take down the whole scheduler). See [Scheduling](scheduling.md).

## Reusing it

The same helpers are available for your own boot wiring:

```php
use Simtabi\Laranail\Package\Tools\Support\Resilience\FailurePolicy;

// wiring that must not fail silently
FailurePolicy::rethrowing(fn () => $this->wireSomethingCritical(), 'my-critical-step');

// wiring that degrades safely
FailurePolicy::swallow(fn () => $this->optionalDecoration(), 'MyFeature', $package->log());
```

---

[← Docs index](../../README.md#documentation)
