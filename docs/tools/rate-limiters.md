# Rate limiters

`$package->registerRateLimiter(...)` declares named rate limiters, applied to Laravel's `RateLimiter` at boot. Pass a raw `Closure` (the thin, unchanged form) or a fluent `Simtabi\Laranail\Package\Tools\Support\Definitions\RateLimiterDefinition` that captures the attempts / key / response pattern so you don't hand-roll the closure.

## Quick start

```php
use Simtabi\Laranail\Package\Tools\Support\Definitions\RateLimiterDefinition;

public function configurePackage(Package $package): void
{
    $package
        ->registerRateLimiter(
            RateLimiterDefinition::make('login')
                ->perMinute(fn (): int => max((int) setting('throttle_attempts', 5), 1))
                ->byField('email'))                 // → strtolower(email) . '|' . ip
        ->registerRateLimiter(
            RateLimiterDefinition::make('two-factor')
                ->perMinute(5)
                ->bySessionKey('login.id'));
}
```

That replaces the equivalent hand-written boilerplate:

```php
RateLimiter::for('login', function ($request) {
    $throttle = (int) setting('throttle_attempts', 5);
    $key = mb_strtolower((string) $request->input('email')) . '|' . $request->ip();
    return Limit::perMinute(max($throttle, 1))->by($key);
});
RateLimiter::for('two-factor', fn ($request) => Limit::perMinute(5)->by((string) $request->session()->get('login.id')));
```

## Windows

Each window method starts a limit; the attempt count is a fixed `int` or a `Closure` resolved per request (so config/setting reads stay lazy).

| Method | `Limit` |
|---|---|
| `perSecond($attempts)` | `Limit::perSecond` |
| `perMinute($attempts)` | `Limit::perMinute` |
| `perMinutes($decay, $attempts)` | `Limit::perMinutes` |
| `perHour($attempts)` | `Limit::perHour` |
| `perDay($attempts)` | `Limit::perDay` |
| `unlimited()` | `Limit::none()` |

## Keys

The key methods bind to the **most-recent** window (so multiple windows can be keyed differently). A window with no key uses Laravel's default (global) key.

| Method | Key |
|---|---|
| `by(string\|Closure)` | a fixed/global string, or your own `Closure(Request): string` |
| `byIp()` | the client IP |
| `byField('email', withIp: true)` | lowercased `input('email')`, with `\|ip` appended by default |
| `bySessionKey('login.id')` | the session value (guarded — empty key on a sessionless request) |
| `byUser()` | the authenticated user id, IP fallback |

## Multiple windows

Chain more than one window to compose several `Limit`s under one name:

```php
RateLimiterDefinition::make('api')
    ->perMinute(60)->byUser()
    ->perDay(10_000)->byIp();
```

## Response and escape hatch

- `->response(fn ($request, $headers) => …)` attaches a custom 429 response to the current window.
- `->using(fn (Request $request) => Limit|Limit[])` bypasses the specs entirely for full control. For a fully custom deny `Response`, use the raw `registerRateLimiter('name', Closure)` form.

## Registration

- `registerRateLimiter(RateLimiterDefinition $definition)` — registered under the definition's own name.
- `registerRateLimiter(string $name, Closure $limiter)` — the raw form (unchanged; a bare string without a closure throws).
- `registerRateLimiters([$def1, $def2, 'legacy' => $closure])` — a batch of definitions and/or `name => Closure` pairs.

Limiters are wired to `RateLimiter::for()` in `bootPackageRateLimiters()` (part of the provider's deferred-boot hooks). Registration order doesn't matter — the named limiter is applied when a request hits it.

---

[← Docs index](../../README.md#documentation)
