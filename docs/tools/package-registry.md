# Package registry

Every package built on `PackageServiceProvider`, what each one claimed, and whether any two claimed
the same name.

```bash
php artisan laranail::package-tools.packages                  # summary table
php artisan laranail::package-tools.packages laranail/captcha # one package, in full
php artisan laranail::package-tools.packages --detail         # every package, in full
php artisan laranail::package-tools.packages --collisions     # only the clashes; exits non-zero if any
php artisan laranail::package-tools.packages --json
```

```
 laranail/captcha  v0.1.0
 One captcha contract for Laravel across Turnstile, hCaptcha, every reCAPTCHA version, …

 Authors:        Simtabi LLC, Imani Manyara, Rahul Dey
 License:        MIT
 Docs:           https://opensource.simtabi.com/documentation/laranail/captcha/
 Config key:     laranail.captcha
 Views:          laranail-captcha
 Translations:   laranail/captcha
 Publish tags:   laranail::captcha-config, laranail::captcha-migrations, …
 Commands:       laranail::captcha.cache-clear, laranail::captcha.doctor, …
```

## Where the description and authors come from

**The package's own `composer.json`** — description, authors, homepage, licence, keywords and
`support.docs`. A package author has to keep that file correct in order to publish at all, so asking
for the same facts through the fluent builder would be a second copy, free to drift from the one
composer enforces.

The builder speaks only where a manifest cannot:

```php
$package->describedAs('Vendor-scoped Artisan commands and publish tags.')  // overrides the manifest
    ->maintainedBy('A Person')                                            // overrides authors
    ->documentedAt('https://docs.example.test')                           // when it is not support.docs
    ->withStability('experimental');                                      // rendered beside the version
```

`withStability()` takes a free string rather than an enum: it is reported, never branched on, and an
enum would make a package's own vocabulary this package's business.

The **Commands** row shows the names a command answers to, not its class — resolved from the console
kernel, so a command whose name is set at construction (which is how the family's
`vendor::slug.command` shape gets past Symfony's validator) reports what you would actually type.

```
+-------------------------+---------+-------------------------+------------------+------------------+----------+
| Package                 | Version | Config                  | Views            | Translations     | Commands |
+-------------------------+---------+-------------------------+------------------+------------------+----------+
| laranail/authkit        | 0.1.0   | laranail.authkit        | —                | —                | 0        |
| laranail/authkit-preset | 0.1.0   | laranail.authkit-preset | —                | —                | 0        |
| laranail/captcha        | 0.1.0   | laranail.captcha        | laranail-captcha | laranail/captcha | 4        |
+-------------------------+---------+-------------------------+------------------+------------------+----------+
```

## The question it answers

Laravel keeps view namespaces, translation namespaces, config keys, publish tags, command names and
middleware aliases in **flat global maps**. Two packages claiming one key do not collide loudly — the
second silently replaces the first, and the failure surfaces far away as a missing view, an
untranslated string, or the wrong file published.

Nothing in the framework will tell you that happened. Each provider records what it claimed as it
registers, so the set can be listed and checked:

```
Name clashes found. The later package silently replaces the earlier one.
  views  shared/space  claimed by: acme/one, acme/two
```

`--collisions` exits non-zero, so it works as a CI gate. A plain listing always exits zero — failing
it would make the command unusable for the thing people run it for most.

## Reading the table

`Views` and `Translations` can legitimately differ, and the example above shows why:
`hasViews('x')` **overrides** the namespace, while `hasTranslations('x')` registers `x` as an
**additional alias** and leaves the canonical namespace alone. A package passing the hyphen form to
both ends up with hyphenated views and a slashed translation namespace. That is not a bug, but it is
worth being able to see.

`Version` reads composer's own runtime data, so a path repository or a package composer did not
install reports `unknown` rather than a stale constant.

## Resolving it directly

```php
use Simtabi\Laranail\Package\Tools\Support\Registry\PackageRegistry;

$registry = app(PackageRegistry::class);
$registry->count();
$registry->all();                       // keyed by provider class
$registry->describe($providerClass);    // one package, flattened
$registry->collisions();                // surface => name => [packages]
```

Entries are keyed by **provider class**, not package name — two providers claiming the same package
name is itself a finding, and keying by name would hide it by overwriting.

> **Resolve it after providers have registered.** The binding is created by the first package to
> register, so resolving `PackageRegistry` before that yields an unbound throwaway with nothing in it.
> Anything real — a command at `handle()` time, an application after boot — is long past that point.

---

[← Docs index](../../README.md#documentation)
