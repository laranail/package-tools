# Testing a package

Extend `IsolatedTestCase` and name your provider:

```php
use Simtabi\Laranail\Package\Tools\Testing\IsolatedTestCase;

class HelloTest extends IsolatedTestCase
{
    protected function getPackageProviders($app): array
    {
        return [HelloServiceProvider::class];
    }

    public function test_it_registers_its_command(): void
    {
        $this->assertCommandExists('acme::hello.sync');
    }
}
```

It auto-discovers nothing, which is the point: what the test boots is what you
listed, so a passing test cannot be leaning on a provider you forgot you had.

## What it adds

`assertCommandExists()`, `assertTableExists()`, `assertTableMissing()`,
`assertColumnExists()`, and `createTempPath()` for anything that writes to disk.

## Assert against the live registry

Grepping the provider proves how a registration was *written*, not what the
container ended up *holding*:

```php
$this->assertContains('acme/hello', array_keys(View::getFinder()->getHints()));
$this->assertContains('acme::hello-config', array_values(ServiceProvider::publishableGroups()));
```

## Do not write into the shared skeleton

Every parallel worker shares one testbench application, and Laravel's
`LoadConfiguration` globs its `config/` directory recursively at boot. A test that
publishes there is read by every other worker that happens to boot while the file
exists — and dies when your `tearDown` removes it.

If a test must publish config, give it its own skeleton by overriding
`applicationBasePath()`. Naming the file per worker is not enough; the directory
is what is shared.

## More

[Isolated test case](../tools/isolated-testcase.md)

---

[← Docs index](../../README.md#documentation)
