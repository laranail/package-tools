# Resilience policy

One failure policy for all package-author boot wiring: **strict in development, lenient in production.** A misconfiguration is always logged; in dev it also rethrows so you catch it before shipping, and in prod it is skipped so one package can never crash the host app at boot.

## The rule

`Simtabi\Laranail\Package\Tools\Support\Resilience\FailurePolicy` decides strictness from `package-tools.resilience.strict` (`PACKAGE_TOOLS_STRICT`):

| Value | Behaviour |
|---|---|
| `true` | strict everywhere — rethrow (fail loud) |
| `false` | lenient everywhere — log + skip |
| `null` (default) | auto: strict everywhere **except** production |

The default keeps development tight (surface every issue before prod) while keeping production resilient.

## Where it applies

The policy governs **package-author boot wiring** — the closures/config you declare that run when the provider boots:

- `configDecorator(...)` closures
- runtime tweaks: `useHttps` / `setLocale` (closure/`*FromConfig` forms)
- route model closures (`registerRouteModel(fn () => …)`)
- closure event subscribers (`event()->addSubscriber(fn ($events) => …)`)
- seeder discovery at boot (a malformed seeder file)
- safe view-composer / view-creator registration
- schedule configuration (`registerScheduledCommand` / `schedulesUsing`) — `scheduling.strict` overrides for scheduling specifically, else it defers here

Each failure is **always logged** (to the package's own logfile when available, else the default channel), then rethrown (strict) or skipped (lenient).

## What it does NOT touch

Infrastructure that must never throw regardless of environment stays unconditionally safe and ignores this setting:

- per-package logging (`$package->log()`)
- the seeder run tracker (best-effort cache writes)
- doctor checks (they report failures as check results)
- the `PackageActions` reporter (an observer — never rethrows)
- the built-in CLI commands (they render their own errors)
- per-seeder **execution** failures (governed by `stopOnFailure()` + the action-event system, not this policy)
- `safelyRegisterComponent()` (collects errors into `getComponentErrors()` by contract)

## Overriding

```dotenv
# force lenient everywhere (e.g. a dev box with an intentionally partial setup)
PACKAGE_TOOLS_STRICT=false

# force strict everywhere (e.g. fail CI/staging hard on any misconfig)
PACKAGE_TOOLS_STRICT=true
```

Or per-environment in `config/package-tools.php` (`resilience.strict`). Scheduling keeps its own `scheduling.strict` override for finer control.

## Reusing it

The same policy is available to your own boot wiring:

```php
use Simtabi\Laranail\Package\Tools\Support\Resilience\FailurePolicy;

FailurePolicy::guard(
    fn () => $this->riskyBootStep(),
    'MyFeature',
    $package->log(),
    ['context' => 'value'],
);
```

---

[← Docs index](../../README.md#documentation)
