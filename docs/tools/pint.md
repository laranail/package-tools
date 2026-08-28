# Shared Pint configuration

`laranail/package-tools` ships the one `pint.json` every package in the family formats against, so
code style is defined in a single file rather than in sixty that drift apart.

## Using it

Add these scripts to the consuming package's `composer.json`:

```json
"scripts": {
    "pint":     "vendor/bin/pint --test --config vendor/laranail/package-tools/pint.json",
    "pint-fix": "vendor/bin/pint --config vendor/laranail/package-tools/pint.json",
    "format":   "@pint-fix"
}
```

The package needs no `pint.json` of its own. Delete it — a local file is what caused the drift this
replaces.

### Why `--config` works from `vendor/`

Pint resolves `exclude` and `cache-file` paths against the **current working directory**, not
against the config file's location. Verified empirically: running package-tools' own suite with an
external config carrying `exclude: ["src/Services"]` dropped 64 files from the report (253 → 189),
which only happens if the exclude is applied relative to the repo being linted.

That is what makes one shared file possible. It also means `pint.json` must **not** be
`export-ignore`d — if it is, `vendor/laranail/package-tools/pint.json` does not exist in a
consumer's install and Pint fails with a file-not-found rather than falling back to a default.

### `--parallel` needs a local socket

`vendor/bin/pint --parallel` opens `tcp://127.0.0.1:0` for its worker pool. It is a large speed win
in CI, but fails with `Operation not permitted (EPERM)` inside restricted sandboxes. If you see
that error, drop the flag; the result is identical, only slower. PHPStan's parallel worker pool
behaves the same way.

## The preset

`laravel`, not `psr12`. PSR-12 is effectively a subset of Laravel's preset, so starting from
`laravel` and adding explicit strictness rules gives the same guarantees without fighting the
conventions the framework's own code follows.

## What each added rule buys

| Rule | Why it is on |
|---|---|
| `declare_strict_types` | Type coercion bugs fail loudly instead of silently converting |
| `fully_qualified_strict_types` | Removes redundant leading slashes once imports exist |
| `global_namespace_import` | Classes, constants and functions are imported rather than inlined FQNs |
| `no_unused_imports` | Dead `use` lines rot; nothing else removes them |
| `ordered_imports` (`length`) | One deterministic order, so import blocks stop churning in diffs |
| `ordered_traits` | Same, for `use` inside a class body |
| `ordered_class_elements` | A predictable reading order: traits, cases, constants, properties, constructor, then methods by visibility |
| `protected_to_private` | Narrows visibility where nothing can be extending it (final classes only) |
| `concat_space` (`one`) | **The single highest-value rule here** — see below |
| `array_syntax` / `array_indentation` | Short arrays, consistently indented |
| `binary_operator_spaces` | `=>` aligned within a block; everything else single-spaced |
| `cast_spaces` | `(int) $x`, never `(int)$x` |
| `single_quote` | Double quotes only where interpolation is actually used |
| `trailing_comma_in_multiline` | Adding a line touches one line, not two |
| `method_argument_space` | A wrapped signature wraps fully, not half |
| `single_line_empty_body` | `public function __construct() {}` on one line |
| `class_attributes_separation` | One blank line between members; none between trait imports |
| `nullable_type_declaration_for_default_null_value` | `?Foo $x = null`, so the nullability is in the signature |
| `no_empty_phpdoc`, `no_empty_comment`, `phpdoc_*` | Docblocks that are aligned, trimmed, and not empty scaffolding |

### `concat_space` is why this file exists

`scripts/verify-dist-integrity.php` was pasted into fourteen packages. All fourteen copies were
byte-identical in logic — but they hashed to **five different values**, and the whole difference
was `$path . '/'` versus `$path.'/'`, plus one PHPDoc alignment. Each package's own `pint.json`
had reformatted the same file its own way.

A copied file cannot satisfy sixty different formatters. Converging the formatter is what makes
converging the file possible.

## Rules deliberately left off

These three are **risky** in PHP-CS-Fixer's own classification: they rewrite code in ways that
change runtime behaviour, not just layout. They are documented here rather than silently omitted,
so nobody re-adds one because it looked harmless.

| Rule | What it rewrites | Why that is dangerous here |
|---|---|---|
| `date_time_immutable` | `DateTime` → `DateTimeImmutable`, and the mutating calls with it | Changes aliasing semantics. Code that relied on mutating a shared instance silently stops working, and `laranail/chrono` is an entire package built on date handling |
| `mb_str_functions` | `strlen()` → `mb_strlen()`, `substr()` → `mb_substr()`, and so on | Swaps **byte** semantics for **character** semantics everywhere at once. Correct for user-facing text, wrong for binary data, hashes and protocol framing — and the fixer cannot tell which a given call is |
| `modernize_types_casting` | `intval($x)` → `(int) $x` | Not equivalent when the second argument is used: `intval($x, 8)` parses as octal, `(int) $x` does not. The fixer rewrites the one-argument form, but the rule is classed risky because the transformation is only conditionally sound |

### Adopting one anyway

If a package genuinely needs one, the change belongs **in this file**, not in a local override —
a local `pint.json` reintroduces exactly the drift this replaces. Before enabling:

1. Run `vendor/bin/pint --test --config <candidate>` across several packages and read the diff, not
   the file count.
2. Run each affected package's suite. A formatting rule that changes behaviour will show up as a
   test failure, which is the entire reason these three are treated differently.
3. For `mb_str_functions` specifically, audit every `strlen`/`substr` call against binary data
   first — there is no automated way to distinguish those.

## Changing the shared config

Edit `pint.json` in this package. Every consumer picks it up on their next `composer update`; there
is nothing to sync and no copies to chase. Expect the first run after a rule change to touch many
files — land that reformat as its own commit, separate from any behavioural change, so review and
`git blame` stay useful.

---

[← Docs index](../../README.md#documentation)
