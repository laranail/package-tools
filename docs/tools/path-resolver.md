# Path resolver

Resolves a path relative to the file that calls it, by an explicit level count and direction.

`PathResolver` replaces the `__DIR__ . '/../../config/db-tools.php'` idiom. That idiom's problem is
not that it is ugly — it is that the dot-dot count is **invisible to everything**. It is correct only
for the file's current depth, nothing checks it, and moving the file leaves a string that still
parses, still looks plausible, and resolves to a path that does not exist. Moving 37 service
providers one directory deeper across this family broke exactly this, in two spellings
(`__DIR__ . '/../…'` and `dirname(__DIR__)`), and every failure surfaced at runtime.

```php
use Simtabi\Laranail\Package\Tools\Support\Path\PathResolver;

// From src/Providers/DbToolsServiceProvider.php, the package root is two levels up.
$config = PathResolver::resolve(
    levels: 2,
    direction: PathResolver::OUTER,
    path: 'config/db-tools.php',
);

// OUTER is the default, so a package reaching for its own root can say only what varies:
$root = PathResolver::resolve(levels: 2);
```

`PathResolver::OUTER` and `PathResolver::INNER` are the `PathDirection` cases themselves, held in
typed class constants rather than copied — so the argument stays type-checked, a `match` over
`PathDirection` remains exhaustive, and a call site needs one import instead of two.

## It resolves from the caller

`PathResolver` reads the calling **file** out of the backtrace and works from that directory. This is
the difference between it and a helper like pheg's `CoreTools::getRootPath()`, which does
`dirname(__DIR__, $levels)` — there `__DIR__` is *CoreTools' own* directory, so every caller gets the
same answer no matter where it sits, and the level count means nothing to the code that passes it.

The calling *file* rather than the calling class is deliberate: a trait's method, a closure, and an
inherited method all report a class whose file is somewhere other than the code doing the counting.

## `levels` is required; `direction` defaults to OUTER

`levels` has no default. It is the value that differs at every call site, and guessing it is the
failure this class exists to remove — so omitting it is a `TypeError` at the call, not a silent 1.

`direction` defaults to `OUTER`, which is what a package reaching for its own root wants and what
almost every replaced `__DIR__ . '/../..'` meant. `INNER` is the deliberate case and is written out.

Every check runs before the filesystem is touched, so a malformed call throws at the call site rather
than returning a wrong-but-usable string.

| Condition | Result |
|---|---|
| `levels` below 1 | `InvalidArgumentException` |
| `..` anywhere in `path` | `InvalidArgumentException` — express the climb with `levels`, not both |
| `Inner` with no `path` | `InvalidArgumentException` — levels alone cannot name a child directory |
| `Inner` where segment count ≠ `levels` | `InvalidArgumentException` |
| `Outer` past the filesystem root | `RuntimeException` |
| No file frame (eval, native callback) | `RuntimeException` |

The root check compares the base directory's **depth** rather than inspecting the result, because
`dirname()` saturates at `/` instead of failing — and `/` is also a legitimate result of a correct
climb from a shallow directory, so the return value cannot distinguish the two.

## Prefer `packageRoot()` where it applies

The level count is itself the fragile part. A marker removes it:

```php
$config = Path::join(PathResolver::packageRoot(), 'config/db-tools.php');
```

`packageRoot()` walks up from the calling file until it finds a directory containing `composer.json`
(or whatever marker you pass), so a file that later moves deeper keeps resolving to the same root —
which is precisely the failure the counted form only *guards* against. Reach for `resolve()` when the
target genuinely is "n directories up" rather than "the package root".

The walk is bounded by the path's depth below its root prefix, so it stops at the drive or UNC share
instead of spinning at `/`, where `dirname()` saturates rather than failing.

## Network paths

Two different questions, with different answers.

**As the base** — a package installed on a network share — is supported. A path is a root prefix plus
segments, and `\\server\share` is a prefix, not two segments. Getting that wrong silently rewrote a
network path into a local absolute one (`\\server\share\pkg` → `/server/share/pkg`) and let a climb
walk above the share. `Path::split()` recognises UNC, Windows drive (`C:\` and the drive-*relative*
`C:foo`), Unix root and relative; `Path::depth()` counts below the prefix, so the share is the floor.

**As the `$path` argument** it is rejected, and stays rejected. That argument frequently originates in
package configuration, which an application can override, and a UNC path there reads from a host the
caller names — on Windows that also hands an NTLM handshake to whoever owns it.

`Path::isNetworkPath()` is available where a caller needs to make that distinction itself.

## Everything a caller passes is untrusted

The `$path` argument frequently originates in package configuration, which a consuming application
can override, so it is validated before the filesystem is touched at all. A `phar://` path reaching a
later `require` is remote code execution, not a wrong directory.

| Rejected | Why it matters |
|---|---|
| `phar://`, `file://`, `data://`, any `scheme:` | a stream wrapper resolves somewhere other than the filesystem; `phar://` deserialises on access |
| A null byte | PHP's path functions are C strings underneath and truncate at it, so `config.php\0.txt` passes an extension check then opens `config.php` |
| An absolute path | it would escape the resolved directory entirely |
| A UNC path (`\\host\share`) | reads from an attacker-controlled network host |
| A Windows drive letter | same escape, different spelling |
| A `..` segment | checked per segment, so a filename like `cache..old` is still allowed |

The scheme check matches the *shape* of a scheme rather than a denylist of known wrappers, because
wrappers can be registered at runtime by any extension or by the application itself — a list of the
dangerous ones is a list of the ones known to be dangerous today. A single letter before the colon is
treated as a drive letter rather than a scheme, since every registered wrapper is longer.

## Descent is verified against the directory, not the string

`Inner` walks one segment at a time with a `FilesystemIterator`, confirming each segment is a real
entry of its parent and canonicalising it with `getRealPath()`. String concatenation would accept a
segment that is a symlink out of the tree; comparing each step against what is actually on disk does
not. The final result is then checked for containment against the starting directory, because a
symlinked segment can be a genuine entry of its parent and still land outside.

`Outer` canonicalises the calling directory with `realpath()` for the same reason: without it a
symlinked package directory makes containment untestable.

## Separators

Never write a separator literal. `Path::join()`, `Path::normalise()`, `Path::segments()`,
`Path::depth()` and `Path::isWithin()` accept either separator and emit the platform's.

Two things get confused under "platform agnostic", and only one is a real bug. **Passing** a path to
PHP is safe with `/` everywhere — Windows accepts it in every filesystem function, so
`__DIR__ . '/../config'` is not worth rewriting on those grounds. **Comparing or slicing** a path is
not: `realpath()`, `SplFileInfo::getRealPath()` and `__DIR__` all return the platform's separator, so
a comparison written against a hardcoded `/` fails on Windows — and in a containment check it fails
by declaring a path outside a boundary it is inside, which is a security decision made on a string
mismatch.

`Path::isWithin()` is separator-aware for two more reasons. A plain `str_starts_with` places `/a/bc`
inside `/a/b`. And on Windows it compares case-insensitively, because the filesystem does — rejecting
`C:\Project\src` as outside `C:\project` would be a security decision made on letter case. Elsewhere
the comparison is exact: Linux is case-sensitive and macOS *can* be, so assuming otherwise is the
unsafe direction.

> Mukora CMS's `DS` constant is the reference for this. Its `switch (PHP_OS)` assigns
> `DIRECTORY_SEPARATOR` in each named branch and `/` in the default, so every branch already agreed —
> and macOS reports `Darwin`, which is not one of the named cases, so it took the default. The switch
> could not change the answer on any platform. The rule it was reaching for is the one kept here.

## Discarding the result is an error

`resolve()` carries `#[\NoDiscard]`. The resolved path is the entire result, so ignoring it performs
no work. Note that PHP raises the diagnostic **at the call**, before the body runs — under a handler
that promotes warnings to exceptions (Pest's, for one) it pre-empts any exception the call would
otherwise throw, so a test asserting a throw must cast `(void)`.

The attribute is inert on PHP 8.4, which this package still supports; the 8.5 cell gets the check.

## Directions

`Outer` climbs toward the filesystem root and then appends `path`, if given. `Inner` descends into
`path`, and requires it to have exactly `levels` segments — the redundancy is the point: the number
and the path have to agree, so a later edit to one without the other fails loudly.

---

[← Docs index](../../README.md#documentation)
