# State and facade guards

Two test assertions that make a framework-native package's rules enforceable: no state carried from one request into the next, and no facade or service-locating helper inside the package's own classes.

`Simtabi\Laranail\Package\Tools\Testing\AssertsNoStateLeak` runs two requests in one container, over
`Simtabi\Laranail\Package\Tools\Testing\StateLeaks`. `Simtabi\Laranail\Package\Tools\Testing\AssertsNoFacadeCalls`
scans source for facade and helper calls, over the scanner `Simtabi\Laranail\Package\Tools\Testing\FacadeCalls`
(callable directly as `FacadeCalls::in($source)`). All four live in `src/`, so a package needs only
`laranail/package-tools` in `require-dev`.

## Why

laranail packages are framework-native: they take Laravel's contracts in their constructors and
bind request-derived services with `scoped()`. Neither rule was checked by anything, and both fail
silently:

- **State leaks only show in production.** Octane boots the application once per worker and keeps
  singletons and statics across requests. Testbench rebuilds the container for every test, so a
  singleton that captured the first request's user or tenant passes every ordinary test.
- **A facade call is a dependency the constructor does not declare.** The class cannot be built
  without a booted container, and nothing in its signature says so.

## No state between requests

```php
use Simtabi\Laranail\Package\Tools\Testing\AssertsNoStateLeak;

uses(AssertsNoStateLeak::class);

it('keeps nothing from one request into the next', function (): void {
    $this->assertNoStateLeaksBetweenRequests(function (string $marker): mixed {
        app(CurrentTenant::class)->set($marker);   // what a middleware would do

        return app(TenantReport::class)->title();   // what the package hands back
    });
});
```

The callback is one request. It receives a marker unique to that request, feeds it in as the
identity, and returns what the package exposes. Between the two requests the assertion does what
Octane does: `forgetScopedInstances()` and a fresh `request` binding. Singletons and statics are
left alone, which is the point.

| Check | Fails when |
|---|---|
| non-vacuity | the first result does not contain the first marker, so the callback never observed its input |
| no leak | the second result still contains the first marker |

## No facade calls

```php
use Simtabi\Laranail\Package\Tools\Testing\AssertsNoFacadeCalls;

uses(AssertsNoFacadeCalls::class);

it('keeps the package classes off facades', function (): void {
    $this->assertNoFacadeCallsIn(dirname(__DIR__) . '/src', minFiles: 10);
});
```

| Rule | Reported |
|---|---|
| `facade` | a static call on a class under `Illuminate\Support\Facades\`, however imported or aliased, or on a global alias such as `\Cache` |
| `helper` | `app`, `resolve`, `config`, `cache`, `request`, `session`, `auth`, `event`, `logger`, `info`, `dispatch`, `broadcast`, `cookie` |

Not reported: any file under a `Providers/` directory (the composition root), a class that extends
`Facade`, `Cache::class`, `now()` (a seam tests control with `travelTo()`), and the rendering helpers
`view()`, `response()`, `route()` and `__()`. A call that cannot be injected stays, marked on its
line or the line above with `// @facade-call-exempt: <reason>`.

The assertion fails below `minFiles`, so a path typo cannot report a clean package. Measured
2026-11-09, the scanner reports 336 calls in this package's own `src/`, 70 in `validation` and 22 in
`chrono`, so adopting it in an existing package is a refactor, not a switch.

---

[← Docs index](../../README.md#documentation)
