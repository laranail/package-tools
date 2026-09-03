# Scaffolding a package

The smallest provider that gives you the family's conventions.

```php
namespace Acme\Hello\Providers;

use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;

class HelloServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('acme/hello')
            ->setPublishTagId('hello')
            ->hasConfigFile('hello')
            ->hasViews();
    }
}
```

Point `composer.json` at it and you have `config('acme.hello.*')` published to
`config/acme/hello.php`, view namespaces `acme/hello::` and `acme-hello::`, and
publish tags `acme::hello-*` — all derived from `->name()`.

## Two things the name decides

`->name()` takes the **composer package name**, not a short slug. Everything else
is derived from it, so `acme/hello` and `laranail/hello` produce different keys
and cannot collide — which is the point, since Laravel's registries are flat maps
that replace silently.

The provider goes in `src/Providers/`. `getPackageBaseDir()` steps out of that
directory before resolving the package root, so `$package->basePath()` still
points where you expect.

## Next

- [Adding a config file](adding-a-config-file.md)
- [Adding an Artisan command](adding-a-command.md)
- A fuller worked example lives in [`docs/examples/`](../examples/HelloPackageServiceProvider.php)

---

[← Docs index](../../README.md#documentation)
