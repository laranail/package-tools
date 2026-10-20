# Test credentials

Generate test passwords, secrets and keys at run time, and fail the suite on any literal left behind.

`Simtabi\Laranail\Package\Tools\Testing\TestCredentials` generates the values;
`Simtabi\Laranail\Package\Tools\Testing\AssertsNoLiteralCredentials` is the trait that guards
against literals, over the scanner `Simtabi\Laranail\Package\Tools\Testing\CredentialLiterals`
(callable directly as `CredentialLiterals::in($source)` outside a test case). All three live in `src/`, so a package needs only `laranail/package-tools` in
`require-dev` to use them.

## Why

A secret scanner cannot tell a test password from a leaked one, and reports both. GitGuardian
raised 28 incidents across laranail by 2026-10-18. 27 were test literals or false positives,
and each was closed by hand. Closing one does not stop the next push raising another.

The scanners match on two things:

- **Shape:** a long string mixing case, digits and symbols.
- **Name:** any literal assigned to something called `password`, `secret` or `token`, however
  weak, and the password in a URL such as `mysql://admin:…@db.example.com`.

A value generated when the suite runs never reaches the repository, so there is nothing to
report.

## Generating values

| Method | Returns |
|---|---|
| `TestCredentials::password(string $purpose = 'default', int $length = 18)` | upper, lower, a digit and a symbol, safe in a DSN, URL or shell |
| `TestCredentials::secret(string $purpose = 'default', int $bytes = 16)` | lower-case hex |
| `TestCredentials::key(string $purpose = 'default', int $bytes = 32)` | raw bytes, e.g. a data key |

Each value is memoised per purpose for the life of the process. Every assertion in one run sees
the same value, and the value changes from run to run. When two values must differ, ask for two
purposes, and assert that they differ:

```php
$theirs = TestCredentials::password('theirs');
$ours   = TestCredentials::password('ours');

expect($theirs)->not->toBe($ours);
```

### Naming purposes

A purpose is one plain lowercase word: `'user'`, `'wrong'`, `'renewed'`. Secret scanning reads
the string passed to `password()` as the password itself when it looks like one. GitGuardian
reported `TestCredentials::password('smtp-new')` and, next to `'username' => 'nope'`,
`TestCredentials::password('unreachable-db')`. Across the 33-repo sweep of 2026-10, no
single-word purpose was reported.

- **When the value need not differ from another, pass no purpose at all.** Then nothing sits in
  that position: `'password' => TestCredentials::password()`.
- **Build a prefixed format or a URL with `sprintf`.** Never concatenate a literal onto a
  generated value:

  ```php
  $key = sprintf('sk_test_%s', TestCredentials::secret('stripe'));
  $dsn = sprintf('mysql://admin:%s@db.example.com/app', rawurlencode(TestCredentials::password('admin')));
  ```

`assertCredentialPurposesAreWordsIn()` enforces the naming rule. It is opt-in, below.

A package with its own generator should use it instead, so test values pass the same rules
production enforces (`laranail/db-console` uses `Password::generate()`).

## Guarding the suite

```php
use Simtabi\Laranail\Package\Tools\Testing\AssertsNoLiteralCredentials;

uses(AssertsNoLiteralCredentials::class);

it('keeps literal credentials out of the tests', function (): void {
    $this->assertNoLiteralCredentialsIn(dirname(__DIR__), minFiles: 20);
});
```

It scans every PHP file under the directories it is given and reports three kinds of literal:

| Rule | Reports |
|---|---|
| `named` | any literal under a name containing `password`, `passwd`, `secret`, `token`, `api_key` or `private_key`: an array key, a variable, a property, a constant, `define()`, a named argument, or a `set()` / `assertSet()` / `Config::set()` key |
| `strong` | a strong-looking literal passed to `new Password(...)` |
| `url` | the password in a URL's userinfo |

It leaves these alone, because they are not credentials:

- the empty string, and anything under four characters;
- prose (anything with whitespace) and masks (`'••••••••'`);
- validation rules: anything with `|`, or a single rule such as `'required'` or `'min:8'`;
- an Eloquent cast (`['password' => 'hashed']`);
- a dotted identifier such as a route or rate-limiter name (`'laranail-auth.password.request'`),
  and a package name or path (`'illuminate/auth'`);
- interpolated strings;
- values passed positionally to a function.

A dotted key counts as a credential's name only when its **last** segment is one.
`config()->set('laranail.password-history.on_store_error', 'degrade')` sets an error mode, and a
filename key like `'…_create_tokens_table.php'` names a file. A key ending in metadata is not a
credential either, for example `token_type`, `password_field`, `secret_ref` or `api_key_provider`.

A `password` key whose value is the word `password` **is** reported, even when it maps a form
field to a column. A
factory's default password has exactly the same syntax, and that default is the literal this
guard exists to catch. Mark a genuine field map as exempt.

To also enforce single-word purposes, add a second test:

```php
it('names every generated credential with a single word', function (): void {
    $this->assertCredentialPurposesAreWordsIn(dirname(__DIR__), minFiles: 20);
});
```

`minFiles` is a floor: a scan that inspects fewer files fails, rather than reporting a clean
suite because a path was wrong. The failure message names the file, line and rule, and only the
first two characters of the value, because it lands in CI logs.

## Deliberate fixed values

Some tests must hold a value exactly:

- an RFC test vector;
- a public key whose fingerprint is asserted;
- a weak input fed to a password-strength rule.

Mark the line above it with the exemption and a reason. The marker also works at the end of the
literal's own line, but avoid that: editing that line puts the literal into the pull request's
diff, where secret scanning reports it, even when the value was already on `main`. In
`laranail/toolkit` that raised two GitGuardian incidents for config values no one had changed.

```php
// @credential-literal-exempt: RFC 7677 test vector
const RFC7677_PASSWORD = '…';
```

Prefer a name that is not a credential's where the test allows it (`RFC7677_SALT`, not
`ADMIN_PASSWORD`). `exemptFiles` skips a whole file by basename. Use it sparingly: a file-wide
skip also covers literals added to that file later.

## Local copies

A repository that cannot depend on package-tools carries a port of the scanner in its own
`NoLiteralCredentialsTest.php`:
- **notifications and enumerator** support PHP 8.3, below package-tools' floor.
- **simtabi/sis-sdk and simtabi/lacommerce** stay independent of the laranail family.

A copy declares itself in its docblock:

```php
/**
 * @credential-guard-copy reason=php-floor-below-package-tools mirrors=0.1.10
 */
```

`reason` is one of two slugs, each a fact about the repository's `composer.json`:

| Slug | Holds when |
|---|---|
| `php-floor-below-package-tools` | `require.php` admits a version below package-tools' floor |
| `outside-laranail` | the package name is not `laranail/*` |

`mirrors` must equal `CredentialLiterals::RULES_VERSION`, the release in which the rules last
changed. `scripts/verify-credential-guard-copies.py` checks every repository in the scheduled
hygiene job. It fails a copy that is undeclared, behind, or whose reason no longer holds;
when a reason stops holding, switch that repository to the shared guard.

`CredentialLiteralsRulesVersionTest` pins a fingerprint of the scanner's source, so changing a
rule without bumping `RULES_VERSION` fails package-tools' own suite.

When you change a rule:
1. Bump `RULES_VERSION` to the release you are cutting.
2. Update both pins in that test.
3. Port the change into each copy, and update its `mirrors=`.

The hygiene job's token reads only public repositories, so a private copy (sis-sdk) is checked
by running the script locally:

```bash
python3 scripts/verify-credential-guard-copies.py --from-checkout ../ --from-checkout ../../../simtabi/packages
```

## Limits

The guard reads PHP source only. Credentials in `phpunit.xml`, `.env.testing` or a workflow file
are outside it. Keep those to values that are not secrets, such as a disposable CI database's
password.

---

[← Docs index](../../README.md#documentation)
