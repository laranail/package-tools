# Failure-handling standard

How code decides to crash, degrade, or continue when an operation fails. The normative standard for every laranail package; `laranail/package-tools` is its reference implementation. The key words MUST / MUST NOT / SHOULD / SHOULD NOT are normative.

## Principle

Failure behaviour is determined by the **consequence** of the failure, not by the environment. The same code path runs in dev, CI, staging, and prod. A failure that is unsafe to continue past crashes everywhere; a failure that is safe to continue past degrades everywhere.

Environment-branched behaviour (`isProduction()`, `APP_ENV`, debug flags) is itself a bug class: the thing that crashes cleanly in dev limps along in prod, so you test one flow and ship another — and the lenient side is almost always production, the one place a masked failure does the most damage. Keying on criticality removes the fork: what you test is what you ship.

## The two classes

Classify by asking: **if execution continues past this failure, is the resulting state safe and correct?**

- **Critical** — continuing leaves an unsafe, incorrect, or insecure state. **Fail fast**: throw and stop at the invalid state. A dead program does less damage than a crippled one.
- **Degradable** — continuing leaves a safe, reduced state. **Report** through the central handler, then continue.

Unclassified failures MUST default to **Critical** (fail closed).

## Rules (abridged, normative)

1. Behaviour MUST NOT branch on environment.
2. Every failure MUST be classified critical or degradable.
3. Handled failures MUST be reported through the central error handler (→ logs **and** monitoring: Sentry/Flare/…). A logfile write is not reporting; swallowing is prohibited.
4. Unclassified failures MUST default to critical.
5. There MUST be no runtime switch that downgrades a critical failure to continue.
6. Fail fast MUST mean fail early and visibly.
7. Degraded state MUST be observable (a health/readiness surface), not only a one-time report.
8. Reporting MUST be guarded — a failure in the reporting path MUST NOT escalate a degradable failure into a crash; fall back to a last-resort local write.
9. Repeated failures SHOULD be throttled (per-request-boot runtimes re-report each request).
10. Operations SHOULD avoid failure-prone I/O and MUST be ordered critical-first, degradable independent.
11. Internal failure detail MUST NOT be rendered to end users.
12. Tests MUST assert one flow; CI MUST gate on a healthy (non-degraded) run.
13. Every report MUST be descriptive and structured (what / expected-vs-actual / cause chain / identifiers / decision / severity); the original cause MUST be preserved in the chain, never flattened.
14. Suspicious-but-non-fatal conditions MUST be logged at warning level (a fired fallback, a second-attempt retry, a tolerated out-of-range input).
15. Secrets and PII MUST be redacted from reports, logs, and degraded-state surfaces.

## How package-tools implements it

`Simtabi\Laranail\Package\Tools\Support\Resilience\FailurePolicy` is the runner — one shape, no environment check:

- `FailurePolicy::run(Closure $work, string $name, BootCriticality $criticality = Critical)` — wraps boot wiring.
- `FailurePolicy::handle(Throwable $e, string $name, BootCriticality $criticality)` — for already-caught failures.
- `FailurePolicy::warn(string $subject, array $context)` — log-only, for rule-14 anomalies.

On failure it wraps the throwable in a `PackageBootException` (naming the builder, preserving the cause, exposing redacted structured `context()`), calls `report()` through the app's exception handler (guarded — a reporting failure falls back to `error_log`), then rethrows if Critical or records the degraded state and continues if Degradable.

`BootReport` (a singleton) is the observable degraded surface (rule 7), storing **redacted** summaries only (builder / criticality / exception class — never raw messages). It is exposed by the `boot:health` doctor check (`laranail::package-tools.doctor`) and by a consumer `/health/boot` route.

### Boot-builder classification

| Builder | Class | Why |
|---|---|---|
| `useHttps` | Critical | HTTP served when HTTPS was required — security |
| route-model binding | Critical | dropped binding → 404s / wrong records |
| closure event subscriber | Critical | default-closed; wires behaviour |
| `configDecorator` | Critical (author may mark Degradable) | a general escape hatch fails closed |
| view composer / creator registration | Critical | a missing composer can break render |
| `setLocale` | Degradable | wrong/default locale is cosmetic |
| seeder discovery | Degradable | the bundle just doesn't seed |
| schedule config | Degradable | skip the bad entry, others register |

### Operational (consumer) wiring

```php
// bootstrap/app.php — throttle boot reports (rule 9)
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->throttle(fn (Throwable $e) => $e instanceof PackageBootException
        ? Limit::perMinute(1)->by('boot:'.$e->builder)
        : null);
})

// an internal health route (rule 7 + 11 — names only, keep it internal)
Route::get('/health/boot', fn (BootReport $r) => $r->isHealthy()
    ? response()->json(['status' => 'ok'])
    : response()->json(['status' => 'degraded', 'builders' => array_keys($r->degraded())], 503));

// a CI gate (rule 12)
$this->assertTrue(app(BootReport::class)->isHealthy());
```

### Documented exemptions (not swallowing)

Some code is the reporting substrate or a domain reporting surface and does not route through `report()`:

- **`PackageLogger`** — the logging substrate cannot report its own failure through itself; its last-resort catch is an `error_log` write (rule 8), not silent.
- **`PackageActionReporter` / `PackageAction*` events** — the package's own domain observability for the migration/seeder/job/schedule lifecycle; a failure surfaces as a structured event a consumer listens to (loud by design).
- **Doctor checks** — they *are* a reporting surface (`run()` returns a result, never throws by contract).
- **`SeederRunTracker`** — best-effort cache writes; a lost status update is tolerated and never escalates.
- **CLI commands** — render their own errors to the console.

---

[← Docs index](README.md#documentation)
