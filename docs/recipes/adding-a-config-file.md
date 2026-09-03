# Adding a config file

Put the file at `config/<name>.php` in your package — **flat**, not nested:

```
your-package/
  config/hello.php
```

```php
$package->name('acme/hello')->hasConfigFile('hello');
```

The application then reads `config('acme.hello.*')`, and
`vendor:publish --tag=acme::hello-config` writes `config/acme/hello.php`.

## The argument is an id, not a key

`hasConfigFile('hello')` does **not** register the key `hello`. It combines the id
with `->name('acme/hello')` to produce `acme.hello`, and publishes to the nested
path. A bare-looking argument here is correct.

The distinction matters because `mergeConfigFrom($path, 'hello')` takes its second
argument **literally** — that one *would* claim a bare global key. Reading a
provider without knowing which is which is how a naming audit gets the answer
backwards in both directions.

## Reading it back

A published override at the nested path is not something Laravel auto-loads, so
package-tools loads it during register and merges it over the packaged defaults.
Edit the published file and it wins.

## More

[Config namespacing](../tools/config-namespacing.md) · [Config manager](../tools/config-manager.md)

---

[← Docs index](../../README.md#documentation)
