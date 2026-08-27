# Dist integrity

Verifies that every path `composer.json` references survives `git archive`, so a package cannot ship a
manifest pointing at a file a dist install will not contain.

## The bug it exists for

`laranail/enumerator` declared `extra.phpstan.includes: ["extension.neon"]` and, in the same
repository, `.gitattributes` carried `/extension.neon export-ignore`. Both lines are individually
reasonable — one tells `phpstan/extension-installer` to load the file, the other reads like ordinary
dev-file housekeeping. Together they ship a package whose generated PHPStan config points at a file
the archive does not contain, and every consumer with `phpstan/extension-installer` gets:

```
Config file .../vendor/laranail/enumerator/extension.neon does not exist or isn't readable
```

naming a path inside `vendor/`, with nothing pointing back at the package that caused it. It survived
for months because it needs two conditions — the installer *and* a dist install — and no package in
the org had both until one did.

`autoload.files` and `bin` are the worse version of the same shape: Composer `require`s those on every
autoload, so a stripped one is a fatal rather than a degraded check.

## Usage

```bash
vendor/bin/laranail-dist-integrity            # audits HEAD
vendor/bin/laranail-dist-integrity v1.2.0     # audits a tag
```

It runs from the **working directory**, so a consumer audits its own repository, not `package-tools`.

The binary **needs no `vendor/`**. It falls back to loading its own classes directly when no autoloader
is present, because a check that guards what a dist install receives must not itself be blocked by a
dependency resolution failure.

In CI:

```yaml
- name: Every referenced path survives the archive
  run: vendor/bin/laranail-dist-integrity
```

## What it checks

Deliberately not every manifest key — only those where a missing file is a failure in the *consumer's*
install rather than a nuisance in the repository:

| Key | Why |
|---|---|
| `extra.phpstan.includes` | `phpstan/extension-installer` generates a config naming it |
| `bin` | Composer links it into `vendor/bin` |
| `autoload.psr-4` / `autoload.psr-0` | a stripped directory autoloads nothing |
| `autoload.files` | `require`d on every autoload — a stripped one is a fatal |

## Three outcomes

| Result | Meaning | Exit |
|---|---|---|
| `ok` | Committed and present in the archive | 0 |
| `?` | Declared in the manifest but never committed. Composer tolerates a missing psr-4 directory, so this is reported, not failed — but it is still a manifest describing something absent | 0 |
| `x` | Committed, and `.gitattributes` strips it from the archive | **1** |

Fix a failure by removing the `export-ignore` rule, **not** by dropping the reference — the file is
referenced because a consumer needs it.

## Why it reads everything from one revision

Manifest and archive both come from the same revision. An earlier version compared the working-tree
`composer.json` against the `HEAD` archive and reported a package mid-refactor as broken — its
manifest already named the new path, its archive still held the old one. In a checkout several people
work in, an audit that flags in-flight work is worse than no audit, because people learn to ignore it.

## Extending it

`DistIntegrityAuditor` takes a `RevisionReader`, so the git access is a seam:

```php
use Simtabi\Laranail\Package\Tools\Support\Dist\DistIntegrityAuditor;
use Simtabi\Laranail\Package\Tools\Support\Dist\GitRevisionReader;

$report = (new DistIntegrityAuditor(new GitRevisionReader(getcwd())))->audit('HEAD');

$report->passed();     // bool
$report->failures();   // list<PathReference>
```

Implement `RevisionReader` to audit something other than a git checkout, or to test without one.

---

[← Docs index](../../README.md#documentation)
