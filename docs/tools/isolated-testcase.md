# IsolatedTestCase

Opinionated Testbench base class for testing a package in isolation.

`Simtabi\Laranail\PackageTools\Testing\IsolatedTestCase` is an
opinionated Testbench wrapper for package test suites:
`abstract class IsolatedTestCase extends Orchestra\Testbench\TestCase`.

## Harness defaults

`defineEnvironment($app)` configures an in-memory test environment:

- `app.key` — a pre-seeded `base64:` key (so encrypted helpers don't
  error in tests).
- `database.default = testing`, with `database.connections.testing` set
  to the `sqlite` driver and `:memory:` database.
- `cache.default = array`, `session.driver = array`,
  `queue.default = sync`.

Subclasses may extend it by calling `parent::defineEnvironment($app)`
and then setting their own keys.

## Registering your provider

The base class does **not** auto-discover any service provider —
consumers state the entry point explicitly by overriding
`getPackageProviders($app)`:

```php
use Simtabi\Laranail\PackageTools\Testing\IsolatedTestCase;

final class FooFeatureTest extends IsolatedTestCase
{
    protected function getPackageProviders($app): array
    {
        return [\Vendor\Foo\FooServiceProvider::class];
    }
}
```

A missing provider therefore fails loudly at setup rather than silently
skipping.

## Helpers

| Method | Purpose |
|---|---|
| `createTempPath(string $suffix = '')` | Create a unique temp directory under `sys_get_temp_dir()` (`laranail-test-<rand>`, optional sanitised suffix). Tracked and recursively removed at `tearDown`. Returns the path. |
| `assertTableExists(string $table, string $message = '')` | Assert `Schema::hasTable($table)`. |
| `assertTableMissing(string $table, string $message = '')` | Assert `! Schema::hasTable($table)`. |
| `assertColumnExists(string $table, string $column, string $message = '')` | Assert `Schema::hasColumn($table, $column)`. |
| `assertCommandExists(string $signature, string $message = '')` | Assert the Artisan command `$signature` is registered (throws `LogicException` if the app isn't booted). |

`tearDown()` removes every path created via `createTempPath()` before
calling `parent::tearDown()`.

## End-to-end example

A Pest test that boots the package, runs its migrations against the
in-memory SQLite connection, and asserts the resulting schema and
command registration:

```php
use Acme\Hello\HelloPackageServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Simtabi\Laranail\PackageTools\Testing\IsolatedTestCase;

final class HelloPackageTest extends IsolatedTestCase
{
    protected function getPackageProviders($app): array
    {
        return [HelloPackageServiceProvider::class];
    }

    public function test_migrations_create_the_hellos_table(): void
    {
        Artisan::call('migrate');

        $this->assertTableExists('hellos');
        $this->assertColumnExists('hellos', 'greeting');
        $this->assertCommandExists('hello');
    }
}
```

Tests extending `IsolatedTestCase` need `orchestra/testbench` `^11.0` in
the package's `require-dev`.

## See also

- [installation.md](../installation.md) — the targets matrix
  (Testbench `^11.0`, Pest `^3.0`).
- [examples/HelloPackageServiceProvider.php](../examples/HelloPackageServiceProvider.php)
  — the provider under test above.
- [examples/HelloPackageTest.php](../examples/HelloPackageTest.php) — an
  end-to-end test extending `IsolatedTestCase` that boots the provider,
  runs a migration, and exercises every helper.

[← Docs index](../../README.md#documentation)
