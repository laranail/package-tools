# AssertsDriverContract

Test helper for the two package defects a mocked test structurally cannot reach.

`Simtabi\Laranail\Package\Tools\Testing\AssertsDriverContract` is a trait for any
Testbench test case. It guards a driver name that resolves to nothing, and a
config key that is read where nothing is registered.

## Why these two

Both are silent, and both survive a fully green suite.

`Illuminate\Support\Manager` resolves a driver by **interpolating its name into a
method name** — `driver('telnyx')` calls `createTelnyxDriver()`. Nothing
type-checks that hop, so a name the config can produce but the manager cannot
build is an `InvalidArgumentException` at the first real use: the first SMS sent,
the first licence verified, the first error page rendered.

`config('foo.key')` on an unregistered key returns `null`, or whatever default
the call site passes. A package reading its own config at the wrong key therefore
runs on inline defaults forever, reporting nothing.

Neither is catchable by a test that supplies the thing under test. A mock answers
whatever the test asked it to. A harness that sets the bare config key creates
the very tree the package is reading, so both sides move together and stay green.
`laranail/authkit-social` shipped two providers that could not authenticate at
all this way; `laranail/env-tools` shipped a config in which every value was
inert, including protected keys that were consequently writable.

> Prefer an exhaustive `match` over an enum to `Manager` for new driver
> resolution — see [Choosing a driver seam](#choosing-a-driver-seam) below. Where
> a `Manager` is genuinely right, ship this guard with it.

## Usage

```php
use Simtabi\Laranail\Package\Tools\Testing\AssertsDriverContract;

uses(AssertsDriverContract::class);

it('can build every driver its config offers', function (): void {
    $shipped = require __DIR__ . '/../../config/sms.php';

    $this->assertEveryDriverIsBuildable(SmsManager::class, array_keys($shipped['drivers']));
});

it('reads config where it registers it', function (): void {
    $this->assertReadsConfigAtRegisteredKey(__DIR__ . '/../../src', 'sms');
});
```

## `assertEveryDriverIsBuildable(string $managerClass, iterable $driverNames)`

Asserts each name resolves to a `create*Driver()` method on the manager. Performs
no I/O and needs no credentials — the question is whether a driver is
*constructible*, not whether the remote service answers.

Names are studly-cased the way `Manager` does it, so `lemon-squeezy` is checked
against `createLemonSqueezyDriver()`.

The assertion **fails on an empty list**. A discovery-driven check that discovers
nothing passes trivially, which is worse than no check because it reads as
coverage.

**Derive the list from the shipped artifact, never by hand.** A hand-written
array tests the array, not the package, and can be wrong on the day it is
written — the first draft of the `sms` guard asserted a `key` setting where the
shipped config spells `api_key`. Good sources: the shipped config file, an
enum's `cases()`, a const map read by reflection.

The source is sometimes indirect, and that is the point. In `laranail/error-pages`
the driver names come from the **key names** under `config('error-pages.panels')`,
because the panel detector returns the key as a render context and
`RenderContext::rendererKey()` ends in `default => $this->context`. Adding
`panels.horizon` silently requires `createHorizonDriver()`; no search for a
string reveals that, but reading the config does.

## `assertReadsConfigAtRegisteredKey(string $sourceDir, string $bareKey, array $exempt = [])`

Asserts no file under `$sourceDir` reads configuration at the bare key. Matches
`config('key…')` and `->get('key…')` for a bare key, a dotted key of any depth,
and an interpolated `"key.{$suffix}"`.

Asserted against the **source**, deliberately, because this defect lives in lines
a test never executes and because a harness that sets the bare key hides it
entirely. Pair it with a behavioural assertion — that a shipped protected key is
actually refused, that the panel can actually be enabled — rather than using it
alone.

`$exempt` lists first key segments that belong to a **different registry** and
keep their own bare names: container tags, gate abilities, queue names. In
`laranail/env-tools` that is `['doctor_rules', 'port_formats', 'audit_sinks',
'observers', 'update']`. Renaming those is a separate breaking decision from
fixing a config key.

> Remember that `hasConfigFile('sms')` takes a **file id, not a key**. The key
> comes from `name()` — see [Namespaced & nested config](config-namespacing.md).
> Namespacing is on by default, so `$bareKey` here is the thing you must *not*
> find, unless the package calls `withoutConfigNamespacing()`.

## Choosing a driver seam

`Manager` is not the only option, and for a name that arrives from configuration
it is often the wrong one. `laranail/captcha` resolves adapters through an
exhaustive `match` on its own `Provider` enum, and says why:

> Not an `Illuminate\Support\Manager`. A Manager resolves by interpolating the
> driver name into a method call […] Here the `Provider` enum is the allow-list:
> a name that is not a case never resolves to anything.

That is the better default:

| | Exhaustive `match` on an enum | `Illuminate\Support\Manager` |
|---|---|---|
| Unknown name | cannot be expressed — the enum rejects it first | resolved by interpolation, fails at runtime |
| New driver | a compile-time-exhaustive `match` arm | a method nothing checks for |
| Third-party extension | needs an explicit seam | `extend()` for free |
| Guard needed | none — the enum is the guard | this trait |

Reach for `Manager` when consumers genuinely need to register their own drivers
at runtime. Otherwise close the set.

## Checking the guard for teeth

A contract test that never fails is decoration. Break what it guards, watch it go
red, restore. This is not ceremony: doing it found a bug in one of these guards —
an early source-scanning pattern anchored the closing quote after the first key
segment, so it matched `env-kit.path` but not `env-kit.encryption.driver`, and
passed while that exact read was still bare.

Where a guard covers more than one list, break it more than one way.

---

[← Docs index](../../README.md#documentation)
