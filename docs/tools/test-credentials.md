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
$theirs = TestCredentials::password('existing-account');
$ours   = TestCredentials::password('new-account');

expect($theirs)->not->toBe($ours);
```

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

- the empty string;
- prose (anything with whitespace);
- validation rules (anything with `|`);
- interpolated strings;
- values passed positionally to a function.

`minFiles` is a floor: a scan that inspects fewer files fails, rather than reporting a clean
suite because a path was wrong. The failure message names the file, line and rule, and only the
first two characters of the value, because it lands in CI logs.

## Deliberate fixed values

Some tests must hold a value exactly:

- an RFC test vector;
- a public key whose fingerprint is asserted;
- a weak input fed to a password-strength rule.

Mark the line, or the line above it, with the exemption and a reason:

```php
// @credential-literal-exempt: RFC 7677 test vector
const RFC7677_PASSWORD = '…';
```

Prefer a name that is not a credential's where the test allows it (`RFC7677_SALT`, not
`ADMIN_PASSWORD`). `exemptFiles` skips a whole file by basename. Use it sparingly: a file-wide
skip also covers literals added to that file later.

## Limits

The guard reads PHP source only. Credentials in `phpunit.xml`, `.env.testing` or a workflow file
are outside it. Keep those to values that are not secrets, such as a disposable CI database's
password.

---

[← Docs index](../../README.md#documentation)
