# Command options

`Commands\Concerns\ReadsOptions` normalises console input. The base
[`Command`](command-naming.md#the-base-command) applies it, so extending that is
enough; `use` the trait directly on a command that already extends something
else.

## Why casting at the call site keeps being wrong

Symfony's accessors are typed `array|bool|string|null`, and what arrives depends
on how the option was declared *and* how it was written on the command line.
Each obvious cast has a case where it is wrong, and in every case the command
keeps running:

| Written | `option()` returns | The obvious cast | Why that is wrong |
|---|---|---|---|
| `--connection=` | `''` | `(string)` → `''` | not a usable connection name, cache key or config segment; anything keyed on it forks |
| `--force=false` | `'false'` | `(bool)` → **`true`** | non-empty string; the flag reads as set exactly when the caller said not to |
| `--limit=twenty` | `'twenty'` | `(int)` → `0` | a plausible-looking limit, port or timeout that passes every downstream check |
| `--tag=a --tag=` | `['a', '']` | `(array)` → `['a', '']` | a stray comma or space becomes a member of the set |

Measured across the family when this trait was written: 54 raw `(bool)` casts,
44 `(string)`, 14 `(array)`, 13 `(int)`.

## The accessors

| Method | Returns | Absent, or given without a value |
|---|---|---|
| `strOption(string $key)` | `?string` | `null` |
| `stringOption(string $key, string $default = '')` | `string` | `$default` |
| `strArg(string $key)` | `string` | `''` |
| `boolOption(string $key)` | `bool` | `false` |
| `intOption(string $key, ?int $default = null)` | `?int` | `$default` |
| `arrayOption(string $key)` | `list<string>` | `[]` |
| `listOption(string $key)` | `list<string>` | `[]` |

```php
use Simtabi\Laranail\Package\Tools\Commands\Command;

final class SyncCommand extends Command
{
    protected $signature = 'laranail::package-tools.sync
        {--connection= : Defaults to the app default}
        {--limit= : How many records}
        {--force : Skip confirmation}
        {--tag=* : Repeatable}
        {--tables= : Comma-separated}';

    public function handle(): int
    {
        $connection = $this->strOption('connection');   // null -> resolve the default yourself
        $format     = $this->stringOption('format', 'table');
        $limit      = $this->intOption('limit', 100);
        $force      = $this->boolOption('force');
        $tags       = $this->arrayOption('tag');        // ['a', 'b']
        $tables     = $this->listOption('tables');      // 'a, b' -> ['a', 'b']

        return self::SUCCESS;
    }
}
```

## Two string accessors, on purpose

They answer different questions, and picking the wrong one is not a style issue:

- **`strOption()`** preserves absence. Use it when `null` means "the caller did
  not choose, so resolve a default from config or the framework" — the shape a
  `--connection=` option needs.
- **`stringOption()`** guarantees a `string`. Use it when the command can always
  proceed and just needs a value.

Writing one in terms of the other is the tell that the wrong one was reached
for. This appeared verbatim in the family:

```php
// This expression is strOption().
$this->stringOption('type') !== '' ? $this->stringOption('type') : null
```

## Notes on specific accessors

**`boolOption()`** is a pass-through for a flag declared `VALUE_NONE`, which
already arrives as a bool. It exists for a flag declared *with* a value and then
written `--force=false`. The false spellings are `false`, `0`, `no` and `off`
(`FILTER_VALIDATE_BOOLEAN`). A value that is neither recognised nor empty counts
as **true**, because the caller did write the flag — `--force=yes` and
`--force=sure` read the same way to a human.

**`intOption()`** returns `$default` rather than `0` for a non-numeric value,
which is the whole difference from `(int)`. A `0` from a typo is indistinguishable
from a deliberate `0`.

**`arrayOption()` vs `listOption()`** — `arrayOption()` reads a repeatable
option (`--tag=a --tag=b`); `listOption()` splits one comma-separated value
(`--tables=a,b`). Both trim, drop empties and re-index. `arrayOption()` also
tolerates the single-value spelling, so a signature that later gains `*` does
not change the call site.

## Compatibility

`stringOption()` was a method on the base `Command` before it moved into this
trait. The base applies the trait, so a subclass sees the same method it always
did.

Its behaviour changed in one case: `--flag=` now falls back to `$default`
instead of returning `''`. The documented fallback previously applied only when
the option was absent entirely, so `stringOption('format', 'csv')` answered `''`
for `--format=`. Pass `''` explicitly to keep an empty string.

---

[← Docs index](../../README.md#documentation)
