# Path resolver

Resolves a path relative to the file that calls it, by an explicit level count and direction.

`PathResolver` replaces the `__DIR__ . '/../../config/db-tools.php'` idiom. That idiom's problem is
not that it is ugly — it is that the dot-dot count is **invisible to everything**. It is correct only
for the file's current depth, nothing checks it, and moving the file leaves a string that still
parses, still looks plausible, and resolves to a path that does not exist. Moving 37 service
providers one directory deeper across this family broke exactly this, in two spellings
(`__DIR__ . '/../…'` and `dirname(__DIR__)`), and every failure surfaced at runtime.

```php
use Simtabi\Laranail\Package\Tools\Support\Path\PathDirection;
use Simtabi\Laranail\Package\Tools\Support\Path\PathResolver;

// From src/Providers/DbToolsServiceProvider.php, the package root is two levels up.
$config = PathResolver::resolve(
    levels: 2,
    direction: PathDirection::Outer,
    path: 'config/db-tools.php',
);
```

## It resolves from the caller

`PathResolver` reads the calling **file** out of the backtrace and works from that directory. This is
the difference between it and a helper like pheg's `CoreTools::getRootPath()`, which does
`dirname(__DIR__, $levels)` — there `__DIR__` is *CoreTools' own* directory, so every caller gets the
same answer no matter where it sits, and the level count means nothing to the code that passes it.

The calling *file* rather than the calling class is deliberate: a trait's method, a closure, and an
inherited method all report a class whose file is somewhere other than the code doing the counting.

## Both arguments are required

There is no default for `levels` or `direction`. A caller that omits the direction is asking for a
path without saying which way to walk, and a helper that guesses is back to the failure this one
exists to remove. Every check runs before the filesystem is touched, so a malformed call throws at
the call site instead of returning a wrong-but-usable string.

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

## Directions

`Outer` climbs toward the filesystem root and then appends `path`, if given. `Inner` descends into
`path`, and requires it to have exactly `levels` segments — the redundancy is the point: the number
and the path have to agree, so a later edit to one without the other fails loudly.

---

[← Docs index](../../README.md#documentation)
