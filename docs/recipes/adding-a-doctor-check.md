# Adding a doctor check

A check is a question about the installed state that a human would otherwise ask
by hand:

```php
$package->hasDoctorCheck(
    DoctorCheckDefinition::make('storage-writable')
        ->label('Storage directory is writable')
        ->run(fn (): bool => is_writable(storage_path('app/hello'))),
);
```

```bash
php artisan package:doctor
```

## Say what to do, not just what failed

A check that reports `false` and stops has moved the problem, not diagnosed it.
Give the failure a remedy the reader can act on — the path that is wrong, the
command that fixes it.

## Classify by consequence

The toolkit separates **Critical** (fail fast; the package cannot work) from
**Degradable** (report and continue). Getting this wrong in either direction is
expensive: a degradable check marked critical takes an application down over a
missing optional cache, and a critical one marked degradable ships a package that
is quietly broken.

## More

[Doctor](../tools/doctor.md) · [Failure handling](../failure-handling.md)

---

[← Docs index](../../README.md#documentation)
